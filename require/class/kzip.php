<?php

/**
 * KZip class
 * @author ShiraNai7 <shira.cz>
 */
class kzip
{

    const
        HEADER = 'KZIP',
        HEADER_LEN = '4',
        VERSION = '1',
        FILE_REAL = 0,
        FILE_TOADD = 1,
        COMPR_NONE = 0,
        COMPR_DEFLATE = 1,
        STREAM_BUFFER = 131072, // 128kB
        STREAM_CBUFFER = 262144, // 256kB
        TRANSFER_BUFFER = 131072 // 128kB
    ;

    public $vars, $handle, $files, $error, $has_zlib, $compr_level;

    /*

    Real file:
    ---------------------
    0   FILE_REAL (0)
    1   offset
    2   length
    3   compression type

    File to add:
    ---------------------
    0   FILE_TOADD (1)
    1   local path

    */

    /**
     * Class constructor
     * @param string $path   path to existing archive or null (= new empty)
     * @param int    $offset offset of the archive in the file
     * @param string
     */
    public function __construct($path = null, $offset = 0)
    {
        // set vars
        $this->files = array();
        $this->has_zlib = extension_loaded('zlib'); // zlib state
        $this->compr_level = 7; // default compression level

        // open and scan archive
        if (isset($path)) {

            // get handle
            if (!is_file($path)) return $this->error = 'File \'' . $path . '\' does not exist or is not a file!';
            $this->handle = fopen($path, 'rb');

            // set offset
            if ($offset !== 0) fseek($this->handle, $offset);

            // check header and version
            if (fread($this->handle, self::HEADER_LEN) !== self::HEADER) return $this->error = 'File \'' . $path . '\' is not a valid KZip archive!';
            if (($ver = fread($this->handle, 1)) !== self::VERSION) return $this->error = 'File \'' . $path . '\' has invalid version! Version ' . $ver . ' detected, but ' . self::VERSION . ' is required!';

            // load vars
            $vars = unpack('V1x', fread($this->handle, 4));
            if ($vars['x'] !== 0) {
                $vars = @unserialize(fread($this->handle, $vars['x']));
                if ($vars === false) return $this->error = 'An error occured while unserializing archive variables!';
                $this->vars = $vars;
            }

            // get files
            while (!feof($this->handle)) {

                // read file headers
                $data = fread($this->handle, 9);
                if (strlen($data) !== 9) {
                    if (feof($this->handle)) break;
                    return $this->error = 'Unexpected end of archive!';
                }
                $head = unpack('V1a/V1b/C1c', $data); // path len, data len, compr. type

                // cannot decompress?
                if (!$this->has_zlib && $head['c'] !== self::COMPR_NONE) {
                    $this->files = array();
                    fclose($this->handle);

                    return $this->error = 'Archive contains compressed files but ZLIB extension is not available!';
                }

                // add file, goto next
                $this->files[fread($this->handle, $head['a'])] = array(self::FILE_REAL, ftell($this->handle), $head['b'], $head['c']);
                if (fseek($this->handle, $head['b'], SEEK_CUR) !== 0) return $this->error = 'Unexpected end of archive!';

            }

        }
    }

    /**
     * Class destructor
     */
    public function __destruct()
    {
        if (isset($this->handle)) {
            fclose($this->handle);
            $this->handle = null;
        }
    }

    /**
     * Add file to the archive
     * @param string $path         path to the file
     * @param string $archive_path path inside the archive, may ommit filename (= end with "/")
     */
    public function addFile($path, $archive_path = '/')
    {
        // check file and archive path
        if (!is_file($path)) return $this->error = 'File \'' . $path . '\' does not exist!';
        if ($archive_path[0] !== '/') return $this->error = 'Archive path must begin with \'/\'!';

        // add
        if (substr($archive_path, -1) === '/') $archive_path .= basename($path);
        $this->files[$archive_path] = array(self::FILE_TOADD, $path);
    }

    /**
     * Add directory to the archive
     * @param  string $dir           path to the directory (must end with directory separator)
     * @param  string $archive_path  path inside the archive (directory, must begin and end with "/")
     * @param  bool   $recursive     add subdirectories as well 1/0
     * @param  mixed  $file_callback optional callback to decide (by returning boolean) whether to add file or not, callback(loc_path, arch_path, fname)
     * @return array  array of empty directories
     */
    public function addDir($dir, $archive_path = '/', $recursive = false, $file_callback = null)
    {
        // check archive path and directory
        if ($archive_path[0] !== '/' || substr($archive_path, -1) !== '/') return $this->error = 'Archive path must begin and end with \'/\'!';
        if (!is_dir($dir)) return $this->error = 'Directory \'' . $dir . '\' does not exist!';

        // scan
        $empty_dirs = array();
        $scan = array(array($dir, $archive_path));
        for ($i = 0; isset($scan[$i]); ++$i) {

            $path = $scan[$i][0];
            $apath = $scan[$i][1];
            $handle = opendir($path);
            $counter = 0;
            while (false !== ($file = readdir($handle))) {
                if ($file === '.' || $file === '..') continue;
                if (is_file($path . $file)) {
                    if (isset($file_callback) && call_user_func($file_callback, $path, $apath, $file) === false) continue;
                    $this->addFile($path . $file, $apath);
                } elseif ($recursive) {
                    $scan[] = array($path . $file . '/', $apath . $file . '/');
                } else {
                    continue;
                }
                ++$counter;
            }
            closedir($handle);

            if ($counter === 0) $empty_dirs[] = $apath;

        }

        return $empty_dirs;
    }

    /**
     * Remove file from the archive
     * @param string $path path of file inside archive (should begin with "/")
     */
    public function removeFile($path)
    {
        unset($this->files[$path]);
    }

    /**
     * Remove directory from the archive
     * @param string $path path to directory inside archive (must begin and end with "/")
     */
    public function removeDir($path)
    {
        // check path
        if ($path[0] !== '/' || substr($path, -1) !== '/') return $this->error = 'Directory path must begin and end with \'/\'!';

        // get and unset files
        $rem = $this->listFilesOnPath($path);
        for($i = 0; isset($rem[$i]); ++$i) unset($this->files[$rem[$i]]);
    }

    /**
     * Check if file exists
     * @param $path path of file inside archive (should begin with "/")
     * @return bool
     */
    public function fileExists($path)
    {
        return isset($this->files[$path]);
    }

    /**
     * Get file from archive
     * @param  string $path path of file inside archive
     * @return string string|bool false on failure
     */
    public function getFile($path)
    {
        // check file
        if (!isset($this->files[$path])) return false;

        // obtain file data
        $stream = $this->getFileStream($path);
        $buffer = '';
        while(!$stream->eof()) $buffer .= $stream->read();
        $stream->free();

        return $buffer;
    }

    /**
     * Get file stream from archive
     *
     * WARNING! You can NOT manipulate multiple "in archive file" streams simultaneously
     * because the streamer uses the archive file resource for that thus any actions
     * done by the streamer (seeking in the archive etc) affects all other streams
     * as well.
     *
     * @param  string          $path path of file inside archive
     * @return KZipStream|bool stream instance or false on failure
     */
    public function getFileStream($path)
    {
        // check and get file
        if (!isset($this->files[$path])) return false;
        $file = $this->files[$path];

        // create and return the stream
        if ($file[0] === self::FILE_REAL) $stream = new KZipStream($this->handle, $file); // real file
        else $stream = new KZipStream(null, $file); // file to add
        return $stream;
    }

    /**
     * Get file size
     * @param  string   $path path of file inside the archive
     * @return int|bool number in bytes or false on failure
     */
    public function getFileSize($path)
    {
        if (isset($this->files[$path])) {
            if ($this->files[$path][0] === self::FILE_REAL) return $this->files[$path][2];
            return filesize($this->files[$path][1]);
        }

        return false;
    }

    /**
     * Extract one file
     * @param  string   $archive_path path inside the archive (must begin with "/" and end with filename)
     * @param  string   $local_path   local path to extract the file to (can ommit filename - end with "/")
     * @param  bool     $overwrite    overwrite existing files 1/0
     * @return int|bool false on failure
     */
    public function extractFile($archive_path, $local_path, $overwrite = false)
    {
        // get file
        if (!isset($this->files[$archive_path])) return false;

        // add filename to local path
        if (substr($local_path, -1) === '/') $local_path .= substr($archive_path, strrpos($archive_path, '/') + 1);

        // check local path
        if (!$overwrite && file_exists($local_path)) return false;

        // extract
        $stream = $this->getFileStream($archive_path);
        $handle = fopen($local_path, 'wb');
        while(!$stream->eof()) fwrite($handle, $stream->read());
        fclose($handle);
        @chmod($local_path, 0777);
        $stream->free();

        return true;
    }

    /**
     * Extract files
     * @param string     $dir          path to local directory to extract files to
     * @param string     $archive_path path inside the archive (directory, must begin and end with "/")
     * @param bool       $overwrite    overwrite existing files 1/0
     * @param bool       $recursive    extract subdirectories as well 1/0
     * @param array|null $skip         array of filenames to skip or null
     */
    public function extractFiles($dir, $archive_path = '/', $overwrite = false, $recursive = false, $skip = null)
    {
        // check directory
        if (substr($dir, -1) !== '/') $dir .= '/';
        if (!is_dir($dir)) return $this->error = 'Directory \'' . $dir . '\' does not exist!';

        // prepare skip map
        if (isset($skip)) $skip = array_flip($skip);

        // extract files
        $scan = array(array($dir, $archive_path));
        for ($i = 0; isset($scan[$i]); ++$i) {

            // get dir, path and files
            $dir = $scan[$i][0];
            $path = $scan[$i][1];
            $files = $this->listFiles($path, true);
            $dirs = array_shift($files);

            // create directory
            if (!file_exists($dir)) mkdir($dir, 0777);

            // extract files
            for($ii = 0; isset($files[$ii]); ++$ii)
                if (($overwrite || !file_exists($dir . $files[$ii])) && !isset($skip, $skip[$files[$ii]])) $this->extractFile($path . $files[$ii], $dir . $files[$ii], $overwrite);

            // add subdir to scan list
            if ($recursive)
                for($ii = 0; isset($dirs[$ii]); ++$ii) $scan[] = array($dir . $dirs[$ii] . '/', $path . $dirs[$ii] . '/');

        }
    }

    /**
     * List files from archive
     * @param  string $path     path to directory inside archive (must end with "/")
     * @param  bool   $get_dirs get dir names as first element of the output 1/0
     * @return array  array of file names (file names only! no path!)
     */
    public function listFiles($path = '/', $get_dirs = false)
    {
        // process dirs arg
        if ($get_dirs) $xdirs = array();

        // check path
        if (substr($path, -1) !== '/') return $this->error = 'Path \'' . $path . '\' is not valid!';
        $path_len = strlen($path);

        // get files
        $output = array();
        foreach ($this->files as $fpath => $data) {
            if (substr($fpath, 0, $path_len) === $path) {

                // add file
                if (($slash = strpos($fpath, '/', $path_len)) === false) {
                    $output[] = substr($fpath, $path_len);
                    continue;
                }

                // add directory
                if ($get_dirs) {
                    $dir = substr($fpath, $path_len, $slash - $path_len);
                    if (!isset($xdirs[$dir])) $xdirs[$dir] = true;
                }

            }
        }

        // directory list
        if ($get_dirs) $output = array_merge(array(array_keys($xdirs)), $output);

        // return
        return $output;
    }

    /**
     * List all files from archive on or under specific path
     * @param  string $path         archive path (must begin and with "/")
     * @param  bool   $get_relative convert paths to relative format 1/0
     * @return array  array of absolute or relative archive file paths
     */
    public function listFilesOnPath($path, $get_relative = false)
    {
        // check path
        if (substr($path, -1) !== '/') return $this->error = 'Path \'' . $path . '\' is not valid!';

        // scan files
        $output = array();
        $path_len = strlen($path);
        $files = array_keys($this->files);
        for($i = 0; isset($files[$i]); ++$i)
            if (substr($files[$i], 0, $path_len) === $path) $output[] = ($get_relative ? substr($files[$i], $path_len) : $files[$i]);

        // return
        return $output;
    }

    /**
     * Get paths of all files
     * @return array
     */
    public function listAll()
    {
        return array_keys($this->files);
    }

    /**
     * Create archive and save to file
     * @param  string|null   $path           file path to save archive to
     * @param  bool          $overwrite      overwrite existing file 1/0
     * @param  bool          $compress       enable compression 1/0
     * @param  resource|null $custom_handler custom file resource to write to or null (= create new)
     * @return bool          false on failure
     */
    public function packToFile($path, $overwrite = false, $compress = false, $custom_handler = null)
    {
        $exists = is_file($path);
        if (!$overwrite && $exists || $exists && !is_writeable($path)) return false;
        if (isset($custom_handler)) $handler = $custom_handler;
        else $handler = fopen($path, 'wb');
        $output = $this->_pack(($compress ? self::COMPR_DEFLATE : self::COMPR_NONE), $handler);
        if (!isset($custom_handler)) {
            fclose($handler);
            @chmod($path, 0777);
        }

        return $output;
    }

    /**
     * Create archive and return as string
     * @param  bool        $compress enable compression 1/0
     * @return string|bool false on failure
     */
    public function packToString($compress = false)
    {
        return $this->_pack($compress ? self::COMPR_DEFLATE : self::COMPR_NONE);
    }

    /**
     * Create archive and print the output
     * @param  bool        $compress enable compression 1/0
     * @return string|bool false on failure
     */
    public function packToOutput($compress = false)
    {
        return $this->_pack($compress ? self::COMPR_DEFLATE : self::COMPR_NONE, -1);
    }

    /**
     * Set compression level
     * @param int $level the level (1-9 for deflate)
     */
    public function setComprLevel($level)
    {
        $level = intval($level);
        if ($level < 1) $level = 1;
        elseif ($level > 9) $level = 9;
        $this->compr_level = $level;
    }

    /**
     * Free resources used by this archive
     */
    public function free()
    {
        $this->__destruct();
    }

    /**
     * Pack files to archive
     * @internal
     *
     * @param int $compr compression type constant value
     * @param resource|int|null file handler, null (= return binary string) or -1 (= print binary string)
     * @return bool|string
     */
    protected function _pack($compr, $handler = null)
    {
        // print mode?
        if ($handler === -1) {
            $print = true;
            $handler = null;
        } else {
            $print = false;
        }

        // create main header
        $buffer = self::HEADER . self::VERSION;
        if (isset($this->vars)) {
            $vars = serialize($this->vars);
            $buffer .= pack('V', strlen($vars)) . $vars;
        } else $buffer .= pack('V', 0);
        if (isset($handler)) {
            fwrite($handler, $buffer);
            $buffer = '';
        }
        if ($print) {
            echo $buffer;
            $buffer = '';
        }

        // turn off compression of not available
        if (!$this->has_zlib) $compr = self::COMPR_NONE;

        // add files
        foreach ($this->files as $path => $file) {

            // pack file
            $pack = $this->_pack_stream($compr, $file);

            // create file header
            $buffer .= pack('VVC', strlen($path), filesize($pack[1]), $compr);
            $buffer .= $path;

            // write
            if (isset($handler)) {
                // to file
                fwrite($handler, $buffer);
                $buffer = '';
                while(!feof($pack[0])) fwrite($handler, fread($pack[0], self::TRANSFER_BUFFER));
            } else {
                // to string or output
                if ($print) {
                    echo $buffer;
                    $buffer = '';
                }
                while (!feof($pack[0])) {
                    $read = fread($pack[0], self::TRANSFER_BUFFER);
                    if ($print) echo $read;
                    else $buffer .= $read;
                }
                $read = '';
            }

            // free temporary file
            fclose($pack[0]);
            unlink($pack[1]);
            _tmpFileCleaned($pack[1]);

        }

        // return
        if (isset($handler) || $print) return true;
        else return $buffer;
    }

    /**
     * Pack file stream
     * @internal
     *
     * @param  int   $compr compression type
     * @param  array $file  file data
     * @return array array(handle, path)
     */
    protected function _pack_stream($compr, $file)
    {
        // get stream
        $stream = new KZipStream(($file[0] === self::FILE_REAL ? $this->handle : null), $file);

        // create temporary file
        $tmp = _tmpFile();

        // read
        while (!$stream->eof()) {

            // load chunk
            if ($compr === self::COMPR_NONE) $read = $stream->read();
            else {
                // compress
                $read = $stream->read(self::STREAM_CBUFFER);
                switch ($compr) {

                    case self::COMPR_DEFLATE;
                        $read = gzdeflate($read, $this->compr_level);
                        break;

                }
                $read = pack('V', strlen($read)) . $read;
            }

            // write to temporary file
            fwrite($tmp[0], $read);
            $read = '';

        }

        // free stream, reset and return tmpfile
        $stream->free();
        fseek($tmp[0], 0);

        return $tmp;
    }

}

/**
 * KZip stream class
 * @author ShiraNai7 (http://shira.cz/)
 */
class KZipStream
{

    public $handle, $file, $in_archive, $pos;
    protected $_c_chunk, $_c_chunk_i, $_c_chunk_len, $_c_chunk_last;

    /**
     * Class constructor
     * @param resource|null $handle archive file resource or null (= file is "to add")
     * @param array         $file   file data array
     */
    public function __construct($handle, $file)
    {
        // set vars
        $this->handle = $handle;
        $this->file = $file;

        // open file
        if (isset($handle)) {
            // file in archive
            $this->in_archive = true;
            $this->pos = $file[1];
            fseek($this->handle, $file[1]);
        } else {
            // "to add" file
            $this->in_archive = false;
            $this->handle = fopen($file[1], 'rb');
            $this->pos = 0;
        }
    }

    /**
     * Reset the stream to the beginning
     */
    public function reset()
    {
        fseek($this->handle, $this->pos = ($this->in_archive ? $this->file[1] : 0));
    }

    /**
     * Read n bytes from the stream
     * @param  int         $n number of bytes to read
     * @return string|bool
     */
    public function read($n = KZip::STREAM_BUFFER)
    {
        // in archive
        if ($this->in_archive) {

            // flat file
            if ($this->file[3] === KZip::COMPR_NONE) {

                // prevent overrun
                if ($this->pos + $n > $this->file[1] + $this->file[2]) $n = ($this->file[1] + $this->file[2]) - $this->pos;
                if ($n === 0) return false;

                // read
                $this->pos += $n;

                return fread($this->handle, $n);

            }

            // compressed file
            if ($this->pos === false) return false;
            return $this->_read_compressed_chunk($n);

        }

        // "to add" file
        $read = fread($this->handle, $n);
        if ($read !== false) $this->pos += $n;
        return $read;
    }

    /**
     * Check if the end of the file is reached
     * @return bool
     */
    public function eof()
    {
        if ($this->in_archive) return ($this->file[3] !== KZip::COMPR_NONE && $this->pos === false || $this->file[3] === KZip::COMPR_NONE && $this->pos === ($this->file[1] + $this->file[2]));
        return feof($this->handle);
    }

    /**
     * Free the stream
     */
    public function free()
    {
        if (!$this->in_archive && null !== $this->handle) {
            fclose($this->handle);
            $this->handle = null;
        }
    }

    /**
     * Read n bytes from compressed file in the archive
     * @internal
     *
     * @param  int    $n number of bytes to return
     * @return string
     */
    protected function _read_compressed_chunk($n)
    {
        // init first chunk
        if (!isset($this->_c_chunk)) {
            $this->_c_chunk_last = false;
            if (!$this->_init_compressed_chunk($n)) $this->_c_chunk_last = true;
        }

        // init next chunk
        if (!isset($this->_c_chunk[$this->_c_chunk_i + $n])) {
            if (!$this->_c_chunk_last) {
                // next chunk
                $append = $this->_c_chunk_len - $this->_c_chunk_i;
                if ($append !== 0) $this->_c_chunk = substr($this->_c_chunk, -$append);
                if (!$this->_init_compressed_chunk($n, $append)) $this->_c_chunk_last = true;
            } else {
                // no more chunks
                $read = substr($this->_c_chunk, $this->_c_chunk_i);
                $this->_c_chunk = $this->_c_chunk_i = $this->_c_chunk_len = $this->_c_chunk_last = null;
                $this->pos = false;

                return $read;
            }

        }

        // read the chunk
        $read = substr($this->_c_chunk, $this->_c_chunk_i, $n);
        $this->_c_chunk_i += $n;

        return $read;
    }

    /**
     * Init compressed chunk
     * @internal
     *
     * @param  int  $n      number of requested bytes in the read operation
     * @param  int  $append number of bytes of appended data (0 = none)
     * @return bool is there another chunk 1/0
     */
    protected function _init_compressed_chunk($n, $append = 0)
    {
        // read chunk size
        $len = unpack('V1x', fread($this->handle, 4));
        $this->pos += ($len['x'] + 4);

        // read and decompress
        $read = fread($this->handle, $len['x']);
        switch ($this->file[3]) {

            case KZip::COMPR_DEFLATE:
                $read = gzinflate($read);
                break;

        }

        // save
        $this->_c_chunk_i = 0;
        $this->_c_chunk_len = strlen($read) + $append;
        if ($append !== 0) $this->_c_chunk .= $read;
        else $this->_c_chunk = $read;

        // return status of next chunk
        return (($this->file[1] + $this->file[2] - $this->pos) !== 0);
    }

}

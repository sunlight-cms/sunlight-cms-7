<?php

/**
 * Database backup class
 * @author ShiraNai7 <shira.cz>
 */
class dbdump
{

    /** @var array|null */
    protected $_import_tmap;

    /**
     * Export database data
     * @param  array|null $tables array of table names (with prefix) or null (= all)
     * @return array      temporary file array(handle, path) containing the data
     */
    public function exportData($tables = null)
    {
        // find all tables
        if (!isset($tables)) $tables = $this->_get_tables();

        // get temporary file
        $file = _tmpFile();

        // vars
        $null = chr(0);
        $nullv = chr(1);
        $prefix_len = (strlen(_mysql_prefix) + 1);

        // headers
        $ver = _checkVersion('database', null, true);
        $ver = end($ver);
        fwrite($file[0], $ver . $null);

        // data
        for ($i = 0; isset($tables[$i]); ++$i) {

            // query
            $q = DB::query('SELECT * FROM `' . $tables[$i] . '`');
            if (DB::size($q) === 0) {
                // skip empty tables
                DB::free($q);
                continue;
            }

            // table header
            $collist = true;
            fwrite($file[0], substr($tables[$i], $prefix_len) . $null);

            while ($r = DB::row($q)) {

                // column list for table header (once)
                if ($collist) {
                    $collist = false;
                    fwrite($file[0], implode($null, array_keys($r)) . $null . $null);
                }

                // row data
                foreach($r as $c) fwrite($file[0], (isset($c) ? DB::esc($c) : $nullv) . $null);

            }

            fwrite($file[0], $null);
            DB::free($q);
            $r = null;

        }

        // return
        return $file;
    }

    /**
     * Import data to the database
     * @param  KZipStream|string $stream KZipStream instance or file path
     * @return array             array(true, skipped_tables) on success, array(false, err_msg) on failure
     */
    public function importData($stream)
    {
        // prepare
        global $_lang;
        $err = null;
        $this->_import_tmap = array();

        // rather ugly hack to use existing file path as KZipStream
        if (is_string($stream)) {
            $file = $stream;
            $stream = new KZipStream(null, array(KZip::FILE_TOADD, $file, null));
            unset($file);
        }

        // vars
        $null = chr(0);
        $nullv = chr(1);
        $version = '';

        // import process
        do {

            // read header
            $offset = 0;
            while (true) {
                ++$offset;
                $byte = $stream->read(1);
                if ($byte === $null) {
                    // header read
                    break;
                } else {
                    $version .= $byte;
                }
                if ($offset > 32) {
                    $err = $_lang['dbdump']['dataerror'];
                    break 2;
                }
            }

            // check version
            if (!_checkVersion('database', $version)) {
                $err = $_lang['dbdump']['badversion'];
                break;
            }

            // find local tables
            $tables = array();
            $q = DB::query('SHOW TABLES LIKE \'' . _mysql_prefix . '-%\'');
            while($r = DB::rown($q)) $tables[$r[0]] = true;
            DB::free($q);
            unset($r);

            // determine maximum query size
            $max_size = DB::query('SHOW VARIABLES LIKE \'max_allowed_packet\'');
            if (DB::size($max_size) !== 1) {
                $err = $_lang['dbdump']['maxpacket'];
                break;
            }
            $max_size = DB::result($max_size, 0, 1);
            $max_size -= 128;
            $max_size = floor(($max_size - 128) * 0.9);

            // adjust maximum query size to available memory
            $memlimit = _phpIniLimit('memory_limit');
            if (isset($memlimit)) {
                $avail_mem = $memlimit - memory_get_usage() - 131072;
                if ($max_size > $avail_mem) $max_size = $avail_mem;
                unset($avail_mem);
            }
            if ($max_size < 32768) {
                $err = $_lang['dbdump']['memory'];
                break;
            }

            // turn off auto_increment for zero values
            DB::query('SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO"');

            // prepare
            $reset = true;
            $skipped_tables = array();
            $stream_buffer = '';
            $stream_buffer_i = 0;

            // import data
            while (true) {

                // reset?
                if ($reset) {
                    $phase = 0;
                    $table = '';
                    $column = '';
                    $columns = array();
                    $columns_size = 0;
                    $values = array();
                    $value = '';
                    $value_counter = 0;
                    $sql = '';
                    $sql_len = 0;
                    $sql_buffer = '';
                    $sql_buffer_len = 0;
                    $skipping_table = false;
                    $reset = false;
                }

                // get 1 byte
                if (!isset($stream_buffer[$stream_buffer_i])) {
                    if ($stream->eof()) break;
                    $stream_buffer = $stream->read();
                    $stream_buffer_i = 0;
                    if ($stream_buffer === '') break;
                }
                $byte = $stream_buffer[$stream_buffer_i];
                ++$stream_buffer_i;

                // phase
                switch ($phase) {

                        /* --  reading table name  -- */
                    case 0:

                        // end of table name?
                        if ($byte === $null) {
                            // read column list
                            $phase = 1;
                            if (!isset($tables[_mysql_prefix . '-' . $table])) {
                                $skipping_table = true;
                                $skipped_tables[] = $table;
                            }
                            break;
                        }

                        // znak nazvu tabulky
                        $table .= $byte;
                        break;

                        /* --  reading column list  -- */
                    case 1:

                        // end of column
                        if ($byte === $null) {
                            if ($column === '') {

                                // end of list, process columns
                                if (!$skipping_table) $columns = '`' . implode('`,`', $columns) . '`';

                                // begin to read rows
                                $phase = 2;

                            } else {
                                // end of column
                                if (!$skipping_table) $columns[] = $column;
                                ++$columns_size;
                                $column = '';
                            }
                            break;
                        }

                        // column name char
                        $column .= $byte;
                        break;

                        /* --  reading row data  -- */
                    case 2:

                        // end of value?
                        if ($byte === $null) {
                            if ($value_counter === 0 && $value === '') {

                                // end of all rows, reset
                                $reset = true;

                                // import remaining data
                                if ($sql_buffer !== '' && !$skipping_table) {
                                    $import = $this->_db_import($table, $columns, $sql_buffer, $sql_buffer_len);
                                    if (isset($import)) {
                                        $err = _htmlStr($import);
                                        break 3;
                                    }
                                }

                            } else {

                                // end of value
                                ++$value_counter;
                                $values[] = $value;
                                $value = '';

                                // end of one row?
                                if ($value_counter === $columns_size) {

                                    if (!$skipping_table) {

                                        // build part of the SQL query
                                        $sql = '(';
                                        for ($i = 0, $lastcol = ($columns_size - 1); isset($values[$i]); ++$i) {
                                            if ($values[$i] === $nullv) $sql .= 'NULL';
                                            else $sql .= '\'' . $values[$i] . '\'';
                                            if ($i !== $lastcol) $sql .= ',';
                                        }
                                        $sql .= ')';

                                        // execute query or use buffer
                                        $sql_len = strlen($sql);
                                        if ($sql_buffer_len + $sql_len + 1 >= $max_size) {
                                            $this->_db_import($table, $columns, $sql_buffer, $sql_buffer_len);
                                            if (isset($import)) {
                                                $err = _htmlStr($import);
                                                break 3;
                                            }
                                        } else {

                                            // separate
                                            if ($sql_buffer !== '') {
                                                $sql_buffer .= ',';
                                                ++$sql_buffer_len;
                                            }

                                            // add query to buffer
                                            $sql_buffer .= $sql;
                                            $sql_buffer_len += $sql_len;

                                        }

                                        // clean up
                                        $sql = '';
                                        $sql_len = 0;

                                    }

                                    $value_counter = 0;
                                    $values = array();

                                }

                            }
                            break;
                        }

                        // value char
                        $value .= $byte;
                        break;

                }

            }

            // restore sql_mode
            DB::query('SET SQL_MODE=""');

        } while (false);

        // void truncate map
        $this->_import_tmap = null;

        // return
        if (!isset($err)) return array(true, $skipped_tables);
        return array(false, $err);
    }

    /**
     * Export database tables (structure only)
     * @param  array|null $tables array of table names (with prefix) or null (= all)
     * @return array      temporary file array(handle, path) containing the table structure
     */
    public function exportTables($tables = null)
    {
        // find all tables
        if (!isset($tables)) $tables = $this->_get_tables();

        // get temporary file
        $file = _tmpFile();

        // export tables
        $sep = chr(0);
        $prefix = chr(1);
        $prefix_off = strlen(_mysql_prefix) + 1;
        for ($i = 0; isset($tables[$i]); ++$i) {
            $q = DB::rown(DB::query('SHOW CREATE TABLE `' . $tables[$i] . '`'));
            $table = substr($tables[$i], $prefix_off);
            fwrite($file[0], 'DROP TABLE IF EXISTS `' . $prefix . '-' . $table . '`' . $sep);
            fwrite($file[0], str_replace('CREATE TABLE `' . $tables[$i] . '`', 'CREATE TABLE `' . $prefix . '-' . $table . '`', $q[1]) . $sep);
        }

        // return
        return $file;
    }

    /**
     * Import database tables (structure only)
     * @param  string $data binary string containing database table structure
     * @return array  array(true, table_num) on success, array(false, err_msg, sql_error) on failure
     */
    public function importTables($data)
    {
        // import
        $sql = '';
        $sep = chr(0);
        $prefix = chr(1);
        $num = 0;
        for ($i = 0; isset($data[$i]); ++$i) {

            // get char
            $char = $data[$i];

            // query end?
            if ($char === $sep) {
                DB::query($sql, true);
                $sql = '';
                ++$num;
                if (($err = DB::error()) !== '') return array(false, $GLOBALS['_lang']['dbdump']['tablesqlerr'], $err);
                continue;
            }

            // prefix placeholder?
            if ($char === $prefix) {
                $sql .= _mysql_prefix;
                continue;
            }

            // add one char
            $sql .= $char;

        }

        // return
        return array(true, $num);
    }

    /**
     * Do database import
     * @return string|null
     */
    protected function _db_import($table, $columns, &$sql_buffer, &$sql_buffer_len)
    {
        // truncate the table
        if (!isset($this->_import_tmap[$table])) {
            DB::query('TRUNCATE TABLE `' . _mysql_prefix . '-' . $table . '`');
            $this->_import_tmap[$table] = true;
        }

        // import
        DB::query('INSERT INTO `' . _mysql_prefix . '-' . $table . '` (' . $columns . ') VALUES ' . $sql_buffer, true);
        if (($err = DB::error()) !== '') return $err;

        // reset vars
        $sql_buffer = '';
        $sql_buffer_len = 0;
    }

    /**
     * Get all system tables
     * @return array
     */
    protected function _get_tables()
    {
        $tables = array();
        $q = DB::query('SHOW TABLES LIKE \'' . _mysql_prefix . '-%\'');
        while($r = DB::rown($q)) $tables[] = $r[0];
        DB::free($q);

        return $tables;
    }

}

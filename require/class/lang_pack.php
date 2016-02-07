<?php

/**
 * Trida pro dynamicke nacitani jazyk. balicku
 * @author ShiraNai7 <shira.cz>
 */
class LangPack implements ArrayAccess
{

    /** @var string */
    protected $key;
    /** @var string */
    protected $dir;
    /** @var array|null */
    protected $list;
    /** @var bool */
    protected $loaded = false;

    /**
     * Konstruktor
     * @param string     $key  pozadovany nazev klice v $_lang promenne
     * @param string     $dir  cesta k adresari s preklady vcetne lomitka na konci
     * @param array|null $list seznam dostupnych lokalizaci (zamezi nutne kontrole pres file_exists)
     */
    public function __construct($key, $dir, array $list = null)
    {
        $this->key = $key;
        $this->dir = $dir;
        $this->list = $list;
    }

    /**
     * Zpracovat test existence klice
     * @param  mixed $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        $this->load();

        return array_key_exists($offset, $GLOBALS['_lang'][$this->key]);
    }

    /**
     * Zpracovat ziskani existence klice
     * @param  mixed $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        $this->load();

        if (array_key_exists($offset, $GLOBALS['_lang'][$this->key])) {
            return $GLOBALS['_lang'][$this->key][$offset];
        } else {
            return null;
        }
    }

    /**
     * Zpracovat nastaveni prvku
     * @throws BadMethodCallException
     * @param  mixed                  $offset
     * @param  mixed                  $value
     */
    public function offsetSet($offset, $value)
    {
        throw new BadMethodCallException;
    }

    /**
     * Zpracovat smazani prvku
     * @throws BadMethodCallException
     * @param  mixed                  $offset
     * @param  mixed                  $value
     */
    public function offsetUnset($offset)
    {
        throw new BadMethodCallException;
    }

    /**
     * Nacist jazykovy balicek
     */
    public function load()
    {
        if ($this->loaded) {
            throw new RuntimeException;
        }

        // zjistit aktualni jazyk
        if (_loginindicator and _language_allowcustom and _loginlanguage != "") {
            $language = _loginlanguage;
        } else {
            $language = _language;
        }

        // sestavit cestu
        $path = $this->dir . $language . '.php';

        // pouzit 'default', pokud neni aktualni jazyk dostupny
        if (null !== $this->list && !in_array($language, $this->list) || null === $this->list && !file_exists($path)) {
            $path = $this->dir . 'default.php';
        }

        // nacist balik
        $GLOBALS['_lang'][$this->key] = (array) include $path;

        $this->loaded = true;
    }

}

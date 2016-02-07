<?php

/**
 * Trida pro cteni ze stromove tabulky
 * @author ShiraNai7 <shira.cz>
 */
class TreeReader
{

    /** @var string */
    protected $table;
    /** @var string */
    protected $childrenIndex;
    /** @var string */
    protected $idColumn;
    /** @var string */
    protected $parentColumn;
    /** @var string */
    protected $levelColumn;
    /** @var string */
    protected $depthColumn;
    /** @var mixed */
    protected $sortBy;
    /** @var bool */
    protected $sortAsc = true;

    /**
     * Konstruktor
     * @param string      $table         nazev tabulky (vcetne pripadneho prefixu a uvozovek, jsou-li treba)
     * @param string|null $childrenIndex nazev indexu pro kolekce potomku uzlu
     * @param string|null $parentColumn  nazev sloupce pro nadrazeny uzel
     * @param string|null $levelColumn   nazev sloupce pro uroven
     * @param string|null $depthColumn   nazev sloupce pro hloubku
     */
    public function __construct($table, $childrenIndex = null, $idColumn = null, $parentColumn = null, $levelColumn = null, $depthColumn = null)
    {
        $this->table = $table;
        $this->childrenIndex = $childrenIndex ?: 'children';
        $this->idColumn = $idColumn ?: 'id';
        $this->parentColumn = $parentColumn ?: 'node_parent';
        $this->levelColumn = $levelColumn ?: 'node_level';
        $this->depthColumn = $depthColumn ?: 'node_depth';
    }

    /**
     * Nacist cestu k danemu uzlu (vypis od korenu k danemu uzlu)
     * @param  array    $columns   pole, ktera maji byt nactena (systemove sloupce jsou nacteny vzdy)
     * @param  int      $nodeId    ID uzlu
     * @param  int|null $nodeLevel uroven uzlu, je-li znama (usetri 1 dotaz)
     * @return array
     */
    public function getPath(array $columns, $nodeId, $nodeLevel = null)
    {
        return $this->loadPath($columns, $nodeId, $nodeLevel);
    }

    /**
     * Nacist strom (strukturovane pole)
     * @param  array       $columns   pole, ktera maji byt nactena (systemove sloupce jsou nacteny vzdy)
     * @param  int|null    $nodeId    ID uzlu
     * @param  int|null    $nodeDepth hloubka uzlu, je-li znama (usetri 1 dotaz, lze vyuzit i jako limit nacitane hloubky)
     * @param  string|null $sortBy    nazev indexu, dle ktereho maji byt uzly serazeny
     * @param  bool        $sortAsc   radit vzestupne 1/0
     * @return array
     */
    public function getTree(array $columns, $nodeId = null, $nodeDepth = null, $sortBy = null, $sortAsc = true)
    {
        // nacist uzly
        $nodes = $this->loadTree($columns, $nodeId, $nodeDepth);

        // sestavit strom
        $tree = array();
        $childrenMap = array();
        foreach ($nodes as &$node) {

            $node[$this->childrenIndex] = array();

            // pridat uzel
            if (null !== $node[$this->parentColumn]) {
                // jako potomka
                if ($node[$this->depthColumn] > 0) {
                    $nodeIndex = array_push($childrenMap[$node[$this->parentColumn]], $node) - 1;
                    $childrenMap[$node[$this->idColumn]] = &$childrenMap[$node[$this->parentColumn]][$nodeIndex][$this->childrenIndex];
                } else {
                    $childrenMap[$node[$this->parentColumn]][] = $node;
                }
            } else {
                // jako koren
                $childrenMap[$node[$this->idColumn]] = &$tree[array_push($tree, $node) - 1][$this->childrenIndex];
            }
        }

        // seradit potomky
        if (null !== $sortBy) {
            $this->sortBy = $sortBy;
            $this->sortAsc = $sortAsc;
            foreach ($childrenMap as &$children) {
                usort($children, array($this, 'sortNodes'));
            }
            usort($tree, array($this, 'sortNodes'));
            $this->sortBy = null;
            $this->sortAsc = true;
        }

        return $tree;
    }

    /**
     * Callback pro razeni potomku dle $this->sortBy a $this->sortAsc
     * @param array $a
     * @param array $b
     * @return int
     */
    public function sortNodes(array $a, array $b)
    {
        return strnatcmp($a[$this->sortBy], $b[$this->sortBy]) * ($this->sortAsc ? 1 : -1);
    }

    /**
     * Nacist plochy strom (vypis uzlu v poradi hierarchie)
     * @param  array       $columns   pole, ktera maji byt nactena (systemove sloupce jsou nacteny vzdy)
     * @param  int|null    $nodeId    ID uzlu
     * @param  int|null    $nodeDepth hloubka uzlu, je-li znama (usetri 1 dotaz, lze vyuzit i jako limit nacitane hloubky)
     * @param  string|null $sortBy    nazev indexu, dle ktereho maji byt uzly serazeny
     * @param  bool        $sortAsc   radit vzestupne 1/0
     * @return array
     */
    public function getFlatTree(array $columns, $nodeId = null, $nodeDepth = null, $sortBy = null, $sortAsc = true)
    {
        // nacist strom
        $tree = $this->getTree($columns, $nodeId, $nodeDepth, $sortBy, $sortAsc);
        if (empty($tree)) {
            return array();
        }

        // traverzovat strom
        $list = array();
        $stack = array();
        $frame = array($tree, 0);
        do {

            for ($i = $frame[1]; isset($frame[0][$i]); ++$i) {

                // ziskat potomky
                $children = $frame[0][$i][$this->childrenIndex];
                unset($frame[0][$i][$this->childrenIndex]);

                // vlozit uzel do seznamu
                $list[] = $frame[0][$i];

                // traverzovat potomky?
                if (!empty($children)) {
                    // prerusit tok a pokracovat potomky
                    $stack[] = array($frame[0], $i + 1);
                    $frame = array($children, 0);
                    continue 2;
                }
            }

            $frame = array_pop($stack);
        } while (null !== $frame);

        return $list;
    }

    /**
     * Sestavit a provest dotaz na strom
     * @param  array    $columns
     * @param  int|null $nodeId
     * @param  int|null $nodeDepth
     * @return array
     */
    public function loadTree(array $columns, $nodeId = null, $nodeDepth = null)
    {
        // zjistit hloubku stromu
        if (null === $nodeDepth) {
            if (null === $nodeId) {
                $nodeDepth = DB::query_row('SELECT MAX(' . $this->depthColumn . ') ' . $this->depthColumn . ' FROM `' . $this->table . '` WHERE ' . $this->levelColumn . '=0');
            } else {
                $nodeDepth = DB::query_row('SELECT ' . $this->depthColumn . ' FROM `' . $this->table . '` WHERE ' . $this->idColumn . '=' . DB::val($nodeId));
            }
            if (false === $nodeDepth) {
                // neexistujici node nebo jina chyba
                throw new RuntimeException(
                    (null === $nodeId) ? 'Nepodarilo se zjistit hloubku stromu' : sprintf('Neexistujici uzel "%s"', $nodeId)
                );
            }
            if (null === $nodeDepth[$this->depthColumn]) {
                // prazdna tabulka
                return array();
            }
            $nodeDepth = $nodeDepth[$this->depthColumn];
        }

        // pripravit sloupce
        $columns = array_merge(
            array($this->idColumn, $this->parentColumn, $this->levelColumn, $this->depthColumn), $columns
        );
        $columnCount = sizeof($columns);

        // sestavit dotaz
        $sql = 'SELECT ';
        for ($i = 0; $i < $columnCount; ++$i) {
            if (0 !== $i) {
                $sql .= ',';
            }
            $sql .= 'r.' . $columns[$i];
        }
        for ($i = 0; $i < $nodeDepth; ++$i) {
            for ($j = 0; $j < $columnCount; ++$j) {
                $sql .= ',n' . $i . '.' . $columns[$j];
            }
        }

        $sql .= ' FROM `' . $this->table . '` r';
        $parentAlias = 'r';
        for ($i = 0; $i < $nodeDepth; ++$i) {
            $nodeAlias = 'n' . $i;
            $sql .= sprintf(
                ' LEFT OUTER JOIN `%s` %s ON(%2$s.%s=%s.%s)', $this->table, $nodeAlias, $this->parentColumn, $parentAlias, $this->idColumn
            );
            $parentAlias = $nodeAlias;
        }
        $sql .= ' WHERE r.';
        if (null === $nodeId) {
            $sql .= $this->levelColumn . '=0';
        } else {
            $sql .= $this->idColumn . '=' . DB::val($nodeId);
        }

        // nacist uzly
        $nodeMap = array();
        $query = DB::query($sql);
        while ($row = DB::rown($query)) {
            for ($i = 0; isset($row[$i]); $i += $columnCount) {
                if (!isset($nodeMap[$row[$i]])) {
                    $nodeMap[$row[$i]] = array();
                    for ($j = 0; $j < $columnCount; ++$j) {
                        $nodeMap[$row[$i]][$columns[$j]] = $row[$i + $j];
                    }
                }
            }
        }
        DB::free($query);

        // seradit uzly
        usort($nodeMap, array($this, 'sortNodesByLevel'));

        return $nodeMap;
    }

    /**
     * Callback pro razeni uzlu dle urovne
     * @param array $a
     * @param array $b
     * @return int
     */
    public function sortNodesByLevel(array $a, array $b)
    {
        if ($a[$this->levelColumn] > $b[$this->levelColumn]) {
            return 1;
        }
        if ($a[$this->levelColumn] == $b[$this->levelColumn]) {
            return 0;
        }

        return -1;
    }

    /**
     * Sestavit a provest dotaz na cestu
     * @param  array    $columns
     * @param  int      $nodeId
     * @param  int|null $nodeLevel
     * @return array
     */
    public function loadPath(array $columns, $nodeId, $nodeLevel = null)
    {
        // zjistit uroven uzlu
        if (null === $nodeLevel) {
            $nodeLevel = DB::query_row('SELECT ' . $this->levelColumn . ' FROM `' . $this->table . '` WHERE ' . $this->idColumn . '=' . DB::val($nodeId));
            if (false === $nodeLevel) {
                throw new RuntimeException(sprintf('Neexistujici uzel "%s"', $nodeId));
            }
            $nodeLevel = $nodeLevel[$this->levelColumn];
        }

        // pripravit sloupce
        $columns = array_merge(
            array($this->idColumn, $this->parentColumn, $this->levelColumn, $this->depthColumn), $columns
        );
        $columnCount = sizeof($columns);

        // sestavit dotaz
        $sql = 'SELECT ';
        for ($i = 0; $i <= $nodeLevel; ++$i) {
            for ($j = 0; $j < $columnCount; ++$j) {
                if (0 !== $i || 0 !== $j) {
                    $sql .= ',';
                }
                $sql .= 'n' . $i . '.' . $columns[$j];
            }
        }
        $sql .= ' FROM `' . $this->table . '` n0';
        for ($i = 1; $i <= $nodeLevel; ++$i) {
            $sql .= sprintf(
                _nl . ' JOIN `%s` n%s ON(n%2$s.%s=n%s.%s)', $this->table, $i, $this->idColumn, $i - 1, $this->parentColumn
            );
        }
        $sql .= ' WHERE n0.' . $this->idColumn . '=' . DB::val($nodeId);

        // nacist uzly
        $nodes = array();
        $nodeIndex = 0;
        $query = DB::query($sql);
        $row = DB::rown($query);
        for ($i = $nodeLevel * $columnCount; isset($row[$i]); $i -= $columnCount) {
            for ($j = 0; $j < $columnCount; ++$j) {
                $nodes[$nodeIndex][$columns[$j]] = $row[$i + $j];
            }
            ++$nodeIndex;
        }
        DB::free($query);

        return $nodes;
    }

}

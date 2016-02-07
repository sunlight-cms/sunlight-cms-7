<?php

/**
 * Trida pro spravu stromove tabulky
 * @author ShiraNai7 <shira.cz>
 */
class TreeManager
{

    /** @var string */
    protected $table;
    /** @var string */
    protected $idColumn;
    /** @var string */
    protected $parentColumn;
    /** @var string */
    protected $levelColumn;
    /** @var string */
    protected $depthColumn;

    /**
     * Konstruktor
     * @param string      $table        nazev tabulky (vcetne pripadneho prefixu, jsou-li treba)
     * @param string|null $parentColumn nazev sloupce pro nadrazeny uzel
     * @param string|null $levelColumn  nazev sloupce pro uroven
     * @param string|null $depthColumn  nazev sloupce pro hloubku
     */
    public function __construct($table, $idColumn = null, $parentColumn = null, $levelColumn = null, $depthColumn = null)
    {
        $this->table = $table;
        $this->idColumn = $idColumn ?: 'id';
        $this->parentColumn = $parentColumn ?: 'node_parent';
        $this->levelColumn = $levelColumn ?: 'node_level';
        $this->depthColumn = $depthColumn ?: 'node_depth';
    }

    /**
     * Zkontrolovat, zda je dany nadrazeny uzel platny pro dany uzel
     * @param  int      $nodeId       ID uzlu
     * @param  int|null $parentNodeId ID nadrazeneho uzlu
     * @return bool
     */
    public function checkParent($nodeId, $parentNodeId)
    {
        return null === $parentNodeId || !in_array($parentNodeId, $this->getChildren($nodeId, true)) && $nodeId != $parentNodeId;
    }

    /**
     * Vytvorit novy uzel
     * @param  array $data
     * @param  bool  $refresh
     * @return int   id noveho uzlu
     */
    public function create(array $data, $refresh = true)
    {
        if (array_key_exists($this->levelColumn, $data) || array_key_exists( $this->depthColumn, $data)) {
            throw new InvalidArgumentException(sprintf('Sloupce "%s" a "%s" nelze manualne definovat', $this->levelColumn, $this->depthColumn));
        }
        $data += array(
            $this->parentColumn => null,
            $this->levelColumn => 0,
            $this->depthColumn => 0,
        );

        $nodeId = DB::insert($this->table, $data, true);
        if ($refresh) {
            $this->doRefresh($nodeId);
        }

        return $nodeId;
    }

    /**
     * Aktualizovat data uzlu
     * @param  int      $nodeId
     * @param  array    $changeset
     * @param  bool     $refresh
     * @return NodeTree
     */
    public function update($nodeId, array $changeset, $refresh = true)
    {
        if (array_key_exists($this->levelColumn, $changeset) || array_key_exists($this->depthColumn, $changeset)) {
            throw new InvalidArgumentException(sprintf('Sloupce "%s" a "%s" nelze manualne menit', $this->levelColumn, $this->depthColumn));
        }
        DB::update($this->table, $this->idColumn . '=' . DB::val($nodeId), $changeset);
        if ($refresh && array_key_exists($this->parentColumn, $changeset)) {
            if (!$this->checkParent($nodeId, $changeset[$this->parentColumn])) {
                throw new RuntimeException(sprintf('Uzel "%s" neni platnym nadrazenym uzlem pro "%s"', $changeset[$this->parentColumn], $nodeId));
            }
            $this->doRefresh($nodeId);
        }

        return $this;
    }

    /**
     * Odstranit uzel
     * @param  int      $nodeId
     * @param  bool     $orphanRemoval
     * @return NodeTree
     */
    public function delete($nodeId, $orphanRemoval = true)
    {
        if ($orphanRemoval) {
            $children = $this->getChildren($nodeId);
            $this->deleteSet($this->idColumn, $children);
        }
        $rootNodeId = $this->getRoot($nodeId);
        DB::query('DELETE FROM `' . $this->table . '` WHERE ' . $this->idColumn . '=' . DB::val($nodeId));
        if ($nodeId != $rootNodeId) {
            $this->doRefreshDepth($rootNodeId, true);
        }

        return $this;
    }

    /**
     * Odstranit vsechny potomky uzlu
     * @param  int      $nodeId
     * @return NodeTree
     */
    public function purge($nodeId)
    {
        $this->deleteSet($this->idColumn, $this->getChildren($nodeId));
        $this->doRefreshDepth($nodeId);

        return $this;
    }

    /**
     * Obnovit urovne stromu
     * @param  int|null $nodeId
     * @return NodeTree
     */
    public function refresh($nodeId = null)
    {
        $this->doRefresh($nodeId);

        return $this;
    }

    /**
     * Odstranit osirele uzly
     * @param  bool     $refresh
     * @return NodeTree
     */
    public function purgeOrphaned($refresh = true)
    {
        do {
            $orphaned = DB::query('SELECT n.' . $this->idColumn . ',n.' . $this->parentColumn . ' FROM `' . $this->table . '` n LEFT JOIN `' . $this->table . '` p ON(n.' . $this->parentColumn . '=p.' . $this->idColumn . ') WHERE n.' . $this->parentColumn . ' IS NOT NULL AND p.id IS NULL');
            $orphanedCount = DB::size($orphaned);
            while ($row = DB::row($orphaned)) {

                // purge children
                $this->deleteSet($this->idColumn, $this->getChildren($row[$this->idColumn], true));

                // purge node and direct children of the nonexistent parent
                DB::query('DELETE FROM `' . $this->table . '` WHERE ' . $this->idColumn . '=' . DB::val($row[$this->idColumn]) . ' OR ' . $this->parentColumn . '=' . DB::val($row[$this->parentColumn]));

            }
            DB::free($orphaned);
        } while ($orphanedCount > 0);

        if ($refresh) {
            $this->doRefresh(null);
        }

        return $this;
    }

    /**
     * Ziskat uroven uzlu dle jeho pozice ve stromu
     * @param  int|null $nodeId
     * @param  array    &$parents
     * @return int
     */
    protected function getLevel($nodeId, array &$parents = null)
    {
        $level = 0;
        $parents = array();
        if (null === $nodeId) {
            return 0;
        }
        do {
            $node = DB::query_row('SELECT ' . $this->parentColumn . ' FROM `' . $this->table . '` WHERE ' . $this->idColumn . '=' . DB::val($nodeId));
            if (false === $node) {
                throw new RuntimeException(sprintf('Neexistujici uzel "%s"', $nodeId));
            }

            $hasParent = (null !== $node[$this->parentColumn]);
            if ($hasParent) {
                $nodeId = $node[$this->parentColumn];
                $parents[] = $nodeId;
                if (++$level > 200) {
                    throw new RuntimeException(sprintf('Dosazen limit 200 urovni zanoreni u nadrazeneho uzlu "%s" - rekurzivni data v tabulce?', $node[$this->parentColumn]));
                }
            }
        } while ($hasParent);

        return $level;
    }

    /**
     * Ziskat korenovy uzel pro dany uzel
     * @param int $nodeId
     */
    protected function getRoot($nodeId)
    {
        $parents = array();
        $this->getLevel($nodeId, $parents);
        if (!empty($parents)) {
            return end($parents);
        }

        return $nodeId;
    }

    /**
     * Ziskat vsechny podrazene uzly (nestrukturovano)
     * @param  int   $nodeId
     * @param  bool  $emptyArrayOnFailure
     * @return array
     */
    protected function getChildren($nodeId, $emptyArrayOnFailure = false)
    {
        // zjistit hloubku uzlu
        $node = DB::query_row('SELECT ' . $this->depthColumn . ' FROM `' . $this->table . '` WHERE id=' . DB::val($nodeId));
        if (false === $node) {
            if ($emptyArrayOnFailure) {
                return array();
            }
            throw new RuntimeException(sprintf('Neexistujici uzel "%s"', $nodeId));
        }
        if (0 == $node[$this->depthColumn]) {
            // nulova hloubka
            return array();
        }

        // sestavit dotaz
        $sql = 'SELECT ';
        for ($i = 0; $i < $node[$this->depthColumn]; ++$i) {
            if (0 !== $i) {
                $sql .= ',';
            }
            $sql .= 'n' . $i . '.id';
        }

        $sql .= ' FROM `' . $this->table . '` r';
        $parentAlias = 'r';
        for ($i = 0; $i < $node[$this->depthColumn]; ++$i) {
            $nodeAlias = 'n' . $i;
            $sql .= sprintf(
                ' LEFT OUTER JOIN `%s` %s ON(%2$s.%s=%s.%s)',
                $this->table,
                $nodeAlias,
                $this->parentColumn,
                $parentAlias,
                $this->idColumn
            );
            $parentAlias = $nodeAlias;
        }
        $sql .= ' WHERE r.' . $this->idColumn . '=' . DB::val($nodeId);

        // nacist potomky
        $query = DB::query($sql);
        $childrenMap = array();
        while ($row = DB::rown($query)) {
            for ($i = 0; isset($row[$i]); ++$i) {
                $childrenMap[$row[$i]] = true;
            }
        }
        DB::free($query);

        return array_keys($childrenMap);
    }

    /**
     * Obnovit strukturove stavy v dane casti stromu
     * @param int|null $currentNodeId
     */
    protected function doRefresh($currentNodeId)
    {
        // zjistit level a rodice aktualniho nodu
        $currentNodeParents = array();
        $currentNodeLevel = $this->getLevel($currentNodeId, $currentNodeParents);

        // pripravit frontu a level set
        $queue = array(
            array(
                $currentNodeId, // id uzlu
                $currentNodeLevel, // uroven uzlu
            ),
        );
        $levelset = array();
        if (null !== $currentNodeId) {
            $levelset[$currentNodeLevel] = array($currentNodeId => true);
        }

        // traverzovat frontu
        for ($i = 0; isset($queue[$i]); ++$i) {

            // traverzovat potomky aktualniho uzlu
            if (null !== $queue[$i][0]) {
                $childCondition = $this->parentColumn . '=' . DB::val($queue[$i][0]);
                $childrenLevel = $queue[$i][1] + 1;
            } else {
                $childCondition = $this->parentColumn . ' IS NULL';
                $childrenLevel = 0;
            }
            $children = DB::query('SELECT ' . $this->idColumn . ',' . $this->levelColumn . ' FROM `' . $this->table . '` WHERE ' . $childCondition);
            while ($child = DB::row($children)) {
                if ($childrenLevel != $child[$this->levelColumn]) {
                    if (isset($levelset[$childrenLevel][$child[$this->idColumn]])) {
                        throw new RuntimeException(sprintf('Rekurzivni zavislost na uzlu "%s"', $child[$this->idColumn]));
                    }
                    $levelset[$childrenLevel][$child[$this->idColumn]] = true;
                }
                $queue[] = array($child[$this->idColumn], $childrenLevel);
            }

            DB::free($children);
            unset($queue[$i]);

        }

        // aplikovat level set
        foreach ($levelset as $newLevel => $childrenMap) {
            $this->updateSet($this->idColumn, array_keys($childrenMap), array($this->levelColumn => $newLevel));
        }

        // aktualizovat hloubku cele vetve
        $topNodeId = end($currentNodeParents);
        if (false === $topNodeId) {
            $topNodeId = $currentNodeId;
        }
        $this->doRefreshDepth($topNodeId, true);
    }

    /**
     * Obnovit stav hloubky v cele vetvi
     * @param int $currentNodeId
     * @parma bool|null $isRootNode
     */
    protected function doRefreshDepth($currentNodeId, $isRootNode = null)
    {
        // zjistit korenovy uzel
        $rootNodeId = $currentNodeId;
        if (true !== $isRootNode && null !== $currentNodeId) {
            $rootNodeId = $this->getRoot($currentNodeId);
        }

        // pripravit frontu a depth mapu
        $queue = array(
            array(
                $rootNodeId, // id uzlu
                0, // uroven uzlu
                array(), // seznam nadrazenych uzlu
            ),
        );
        $depthmap = array();

        // traverzovat frontu
        for ($i = 0; isset($queue[$i]); ++$i) {

            // vyhledat potomky
            if (null !== $queue[$i][0]) {
                $childCondition = $this->parentColumn . '=' . DB::val($queue[$i][0]);
            } else {
                $childCondition = $this->parentColumn . ' IS NULL';
            }
            $children = DB::query($s = 'SELECT ' . $this->idColumn . ',' . $this->depthColumn . ' FROM `' . $this->table . '` WHERE ' . $childCondition);
            if (DB::size($children) > 0) {
                // uzel ma potomky, pridat do fronty
                if (null !== $queue[$i][0]) {
                    $childParents = array_merge(array($queue[$i][0]), $queue[$i][2]);
                } else {
                    $childParents = $queue[$i][2];
                } while ($child = DB::row($children)) {
                    $queue[] = array($child[$this->idColumn], $child[$this->depthColumn], $childParents);
                }
            }
            DB::free($children);

            // aktualizovat urovne nadrazenych uzlu
            if (null !== $queue[$i][0] && !isset($depthmap[$queue[$i][0]])) {
                $depthmap[$queue[$i][0]] = 0;
            }
            for ($j = 0; isset($queue[$i][2][$j]); ++$j) {
                $currentDepth = $j + 1;
                if (!isset($depthmap[$queue[$i][2][$j]]) || $depthmap[$queue[$i][2][$j]] < $currentDepth) {
                    $depthmap[$queue[$i][2][$j]] = $currentDepth;
                }
            }
            unset($queue[$i]);

        }

        // aplikovat depth mapu
        foreach ($depthmap as $nodeId => $newDepth) {
            DB::update($this->table, $this->idColumn . '=' . DB::val($nodeId), array($this->depthColumn => $newDepth));
        }
    }

    /**
     * Aktualizovat set dat v tabulce
     * @param string $column
     * @param array  $set
     * @param array  $changeset
     * @param int    $maxPerQuery
     */
    protected function updateSet($column, array $set, array $changeset, $maxPerQuery = 100)
    {
        if (!empty($set)) {
            foreach (array_chunk($set, $maxPerQuery) as $chunk) {
                DB::update(
                    $this->table,
                    $column . ' IN(' . DB::arr($chunk) . ')',
                    $changeset,
                    null
                );
            }
        }
    }

    /**
     * Odstranit set dat z tabulky
     * @param string $column
     * @param array  $set
     * @param int    $maxPerQuery
     */
    protected function deleteSet($column, array $set, $maxPerQuery = 100)
    {
        if (!empty($set)) {
            foreach (array_chunk($set, $maxPerQuery) as $chunk) {
                DB::query('DELETE FROM `' . $this->table . '` WHERE ' . $column . ' IN(' . DB::arr($chunk) . ')');
            }
        }
    }

}

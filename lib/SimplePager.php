<?php

class SimplePager {
    public $limit;      // Page size
    public $page;       // Current page
    public $item_count; // Total item count
    public $page_count; // Total page count
    public $result;     // Result set (array of records)
    public $count;      // Item count on the current page

    public function __construct($query, $params, $limit, $page) {
        global $_db;

        // Set [limit] and [page]
        $this->limit = is_numeric($limit) ? max((int)$limit, 1) : 10;
        $this->page = is_numeric($page) ? max((int)$page, 1) : 1;

        // Set [item count]
        // Build a safe count query by wrapping the original query as a subquery
        // Remove any trailing ORDER BY to avoid syntax issues in some DB engines
        $countSource = preg_replace('/ORDER\s+BY[\s\S]*$/i', '', $query);
        $q = "SELECT COUNT(*) FROM (" . $countSource . ") AS _pcount";
        $stm = $_db->prepare($q);
        $stm->execute($params);
        $this->item_count = (int) $stm->fetchColumn();

        // Set [page count]
        $this->page_count = ceil($this->item_count / $this->limit);

        // Calculate offset
        $offset = ($this->page - 1) * $this->limit;

        // Set [result]
        $stm = $_db->prepare($query . " LIMIT $offset, $this->limit");
        $stm->execute($params);
        $this->result = $stm->fetchAll();

        // Set [count]
        $this->count = count($this->result);
    }

    public function html($href = '', $attr = '') {
        if (!$this->result) return;

        // Generate pager (html)
        $prev = max($this->page - 1, 1);
        $next = min($this->page + 1, $this->page_count);

        echo "<nav class='pager' $attr>";
        echo "<a href='?page=1&$href'>First</a>";
        echo "<a href='?page=$prev&$href'>Previous</a>";

        for ($p = 1; $p <= $this->page_count; $p++) {
            $c = $p == $this->page ? 'active' : '';
            echo "<a href='?page=$p&$href' class='$c'>$p</a>";
        }

        echo "<a href='?page=$next&$href'>Next</a>";
        echo "<a href='?page=$this->page_count&$href'>Last</a>";
        echo "</nav>";
    }
}
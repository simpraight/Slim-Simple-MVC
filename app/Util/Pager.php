<?php

namespace SlimMVC\Util;

/**
 * Pager class
 *
 * @package SlimMVC\Util
 * @author  Shinya Matsushita <simpraight@gmail.com>
 * @license MIT License.
 */
class Pager
{
    /**
     * Current page number
     * @var int
     */
    private $page_current = 0;
    /**
     * Total element count
     * @var int
     */
    private $count_total = 0;
    /**
     * element count per page
     * @var int
     */
    private $count_per_page = 0;


    public function __construct($currentPage, $countTotal, $countPerPage)
    {
        $this->page_current = intval($currentPage);
        $this->count_total = intval($countTotal);
        $this->count_per_page = intval($countPerPage);
    }

    /**
     * hasPages
     *
     * @return bool
     */
    public function hasPages()
    {
        return $this->isValid() && (1 < $this->last());
    }

    /**
     * pages
     *
     * @return array
     */
    public function pages()
    {
        $ret = array();

        $m = 3*2+1+2;
        $l = $this->last();
        $c = $this->page_current;
        $s = max(1, min($l - 7, $c - 3));
        $e = min($l, ($c < 5 ? 7 : $c + 3));

        $ret[] = array('num' => 1, 'current' => ($this->page_current == 1), 'skip' => false);
        for ($i = 2; $i < $l; $i++)
        {
            if ($i+1 == $s) { $ret[] = array('num' => $i, 'current' => false, 'skip' => true); }
            else if ($i-1 == $e) { $ret[] = array('num' => $i, 'current' => false, 'skip' => true); }
            else if ($s<=$i && $i<=$e) { $ret[] = array('num' => $i, 'current' => ($i == $this->page_current), 'skip' => false); }
        }
        $ret[] = array('num' => $l, 'current' => ($this->page_current == $l), 'skip' => false);

        return $ret;
    }

    /**
     * first page number
     *
     * @return int
     */
    public function first()
    {
        return $this->isValid() ? 1 : false;
    }

    /**
     * prev page number
     *
     * @return int
     */
    public function prev()
    {
        return ($this->isValid() && (1 < $this->page_current)) ? $this->page_current - 1 : false;
    }

    /**
     * next page number
     *
     * @return int
     */
    public function next()
    {
        return ($this->isValid() && ($this->page_current < $this->last())) ? $this->page_current + 1 : false;
    }

    /**
     * last page number
     *
     * @return int
     */
    public function last()
    {
        return $this->isValid() ? ceil($this->count_total / $this->count_per_page) : false;
    }

    /**
     * total elment count
     *
     * @return int
     */
    public function total()
    {
        return $this->count_total;
    }

    /**
     * is valid pager
     *
     * @return bool
     */
    private function isValid()
    {
        if ($this->count_total < 1) { return false; }
        if ($this->count_per_page < 1) { return false; }
        if ($this->page_current < 1) { return false; }
        return true;
    }
}

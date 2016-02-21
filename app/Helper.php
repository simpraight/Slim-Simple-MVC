<?php
namespace SlimMVC;

/**
 * Helper
 *
 * @package Helper
 * @author  Shinya Matsushita <simpraight@gmail,com>
 * @license MIT License.
 * @see \Twig_Extention
 */
abstract class Helper extends \Twig_Extension
{
    /**
     * helper name
     *
     * @var string
     */
    protected $_name = 'base';
    /**
     * list of export functions.
     *
     * @var array
     */
    protected $_functions = array();
    /**
     * list of export filters.
     *
     * @var array
     */
    protected $_filters = array();

    /**
     * {@inheritdoc}
     *
     * @return string _name property
     */
    final public function getName()
    {
        return $this->_name;
    }

    /**
     * {@inheritdoc}
     *
     * @return array exported functions
     */
    final public function getFunctions()
    {
        $functions = array();
        foreach ($this->_functions as $method => $func)
        {
            $functions[] = new \Twig_SimpleFunction($func, array($this, $method));
        }
        return $functions;
    }

    /**
     * {@inheritdoc}
     *
     * @return array exported filters
     */
    final public function getFilters()
    {
        $filters = array();
        foreach ($this->_filters as $method => $func)
        {
            $filters[] = new \Twig_SimpleFilter($func, array($this, $method));
        }
        return $filters;
    }
}

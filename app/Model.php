<?php
namespace SlimMVC;

/**
 * Base Model
 *
 * @package Model
 * @author  Shinya Matsushita <simpraight@gmail.com>
 * @license MIT License
 */
class Model extends \Model
{
    /**
     * List of validation errors per key name.
     *
     * @var array
     */
    protected $__errors = array();

    /**
     * Plugin object
     *
     * @var \SlimMVC\Plugin\Model\ModelPlugin
     */
    protected $_plugin = null;

    /**
     * Transaction nested level counter.
     *
     * @var (array)int
     */
    protected static $__trans_counter;

    /**
     *  Transaction has failure
     *
     *  @var (array)bool
     */
    protected static $__trans_has_failure;

    /**
     * Database connection name.
     *
     * @var string
     */
    public static $_connection_name = \ORM::DEFAULT_CONNECTION;


    /**
     * override \Model::factory
     *   Set _connection_name (static property).
     *   {@inheritdoc}
     *
     * @param string $class_name
     * @param string $connection_name
     * @return void
     */
    public static function factory($class_name, $connection_name = null)
    {
        $class_name = preg_replace('/^.*SlimMVC\\\\Model\\\\/', '', $class_name);
        $_class_name = '\\SlimMVC\\Model\\'.$class_name;
        $connection_name = is_null($connection_name) ? $_class_name::$_connection_name : $connection_name;
        $_class_name::$_connection_name = $connection_name;
        database($connection_name);

        return parent::factory($class_name, $connection_name);
    }

    /**
     * resetErrors
     *
     * @return void
     */
    final private function resetErrors()
    {
        $this->__errors = array();
    }

    /**
     * add error message by keyname
     *
     * @param string $key
     * @param string $message
     * @return void
     */
    final public function addError($key, $message)
    {
        if (!isset($this->__errors[$key])) { $this->__errors[$key] = array(); }
        $this->__errors[$key][] = $message;
    }

    /**
     * return list of all errors.
     *
     * @return array
     */
    final public function getErrors()
    {
        return $this->__errors;
    }

    /**
     * return list of errors by keyname
     *
     * @param string $key
     * @return array
     */
    final public function getErrorsOn($key)
    {
        return isset($this->__errors[$key]) ? $this->__errors[$key] : array();
    }

    /**
     * return errors as string by keyname
     *
     * @param string $key
     * @return string
     */
    final public function getErrorMessageOn($key, $delim = null)
    {
        if (is_null($delim) || !is_string($delim)) { $delim = ','; }
        $errors = $this->getErrorsOn($key);
        return count($errors) ? join($delim, $errors) : '';
    }

    /**
     * Has errors by keyname
     *
     * @param string $key
     * @return bool
     */
    final public function hasError($key = null)
    {
        if (is_null($key)) { return (0 < count($this->__errors)); }
        else { return isset($this->__errors[$key]) && (0 < count($this->__errors)); }
    }

    /**
     * Override \Model::set method.
     *   if key is own property, no set a "is_dirty" flag.
     *
     * @param string||array $key
     * @param mixed $value
     * @return self
     */
    final public function set($key, $value = null)
    {
        $fields = array();
        if (!is_array($key)) { $key = array($key => $value); }
        foreach ($key as $k => $v)
        {
            if (property_exists($this, $k))
            {
                //$this->_data[$k] = $v;
                $this->{$k} = $v;
            }
            else if (isset($this->_fields))
            {
                if (in_array($k, $this->_fields)) { $fields[$k] = $v; }
            }
            else
            {
                $fields[$k] = $v;
            }
        }
        return parent::set($fields);
    }


    /**
     * Ignore specified field.
     *
     * @param string $field
     * @return void
     */
    final public function ignore($field)
    {
        if (!is_string($field)) return;
        unset($this->orm->$field);
    }

    /**
     * Overrided \Model::save method.
     *   Added, calling validation and callback methods automatically.
     *   for example,
     *
     *       model('User')->set(array(...))->save();
     *
     *    Then, the following method will call automatically.
     *
     *         1. beforeValidationOnCreate()
     *         2. beforeValidation()
     *         3. validateOnCreate()
     *         4. validate()
     *         5. afterValidateionOnCreate()
     *         6. afterValidation()
     *         7. beforeCreate()
     *         8. beforeSave()
     *         9. parent::save()
     *        10. afterCreate()
     *        11. afterSave()
     *
     * @return bool save result
     */
    final public function save()
    {
        $n = $this->is_new();
        $beforeValidation = $n ? 'beforeValidationOnCreate' : 'beforeValidationOnUpdate';
        $afterValidation = $n ? 'afterValidationOnCreate' : 'afterValidationOnUpdate';
        $validate = $n ? 'validateOnCreate' : 'validateOnUpdate';
        $beforeSave = $n ? 'beforeCreate' : 'beforeUpdate';
        $afterSave = $n ? 'afterCreate' : 'afterUpdate';

        $this->resetErrors();
        $this->loadPlugin();

        if (!$this->{$beforeValidation}())
        {
            $this->addError('__save__', 'Callback error on ' . $beforeValidation);
            return false;
        }
        if (!$this->beforeValidation())
        {
            $this->addError('__save__', 'Callback error on beforeValidation');
            return false;
        }

        $this->{$validate}();
        $this->callPlugin($validate);
        $this->validate();
        $this->callPlugin('validate');

        if ($this->hasError())
        {
            $this->addError('__save__', 'Validation error');
            return false;
        }

        if (!$this->{$afterValidation}())
        {
            $this->addError('__save__', 'Callback error on ' . $afterValidation);
            return false;
        }
        if (!$this->afterValidation())
        {
            $this->addError('__save__', 'Callback error on afterValidation');
            return false;
        }

        $this->startTrans();
        try
        {

            if (!$this->{$beforeSave}())
            {
                $this->failTrans();
                $this->addError('__save__', 'Callback error on ' . $beforeSave);
                return false;
            }
            if (!$this->beforeSave())
            {
                $this->failTrans();
                $this->addError('__save__', 'Callback error on beforeSave');
                return false;
            }

            if ($n && isset($this->created_at) && empty($this->created_at)) { $this->created_at = time(); }
            if (!$n && isset($this->updated_at)) { $this->updated_at = time(); }

            $result = parent::save();

            if (!$result)
            {
                $this->failTrans();
                $this->addError('__save__', 'save error');
                return false;
            }

            if (!$this->{$afterSave}())
            {
                $this->failTrans();
                $this->addError('__save__', 'Callback error on ' . $afterSave . '. rollback changes.');
                if ($n) { $this->id = null; }
                return false;
            }
            if (!$this->afterSave())
            {
                $this->failTrans();
                $this->addError('__save__', 'Callback error on afterSave. rollback changes.');
                if ($n) { $this->id = null; }
                return false;
            }

            return $this->completeTrans();
        }
        catch (\Exception $e)
        {
            $this->failTrans();
            $this->addError('__save__', $e->getMessage());
            if ($n) { $this->id = null; }
            return false;
        }
    }

    /**
     * Override \Model::delete  method.
     *   Added, calling callback methods automatically and soft deleting.
     *   for example,
     *
     *      orm('User')->find_one(1)->delete();
     *
     *    Then, the following method will call automatically.
     *
     *        1. beforeDelete()
     *        2. parent::delete()
     *        3. afterDelete()
     *
     * @return bool delete result
     */
    final public function delete()
    {
        if ($this->is_new()) { return false; }

        $this->loadPlugin();
        $this->startTrans();
        try
        {
            if (!$this->beforeDelete())
            {
                $this->failTrans();
                $this->addError('__delete__', 'Callback error on beforeDelete');
                return false;
            }

            $result = parent::delete();

            if (!$result)
            {
                $this->failTrans();
                $this->addError('__delete__', 'delete error');
                return false;
            }

            if (!$this->afterDelete())
            {
                $this->failTrans();
                $this->addError('__delete__', 'Callback error on afterDelete.');
                return false;
            }

            return $this->completeTrans();
        }
        catch (\Exception $e)
        {
            $this->failTrans();
            $this->addError('__delete__', $e->getMessage());
            return false;
        }
    }

    /**
     *  Disable method.
     *
     *      orm('User')->find_one(1)->disable();
     *
     *    Then, the following method will call automatically.
     *
     *        1. beforeDisable()
     *        2. disable()
     *        3. afterDisable()
     *
     * @return bool disable result
     */
    final public function disable()
    {
        if ($this->is_new()) { return false; }

        $this->loadPlugin();
        $this->startTrans();
        try
        {
            if (!$this->beforeDisable())
            {
                $this->failTrans();
                $this->addError('__disable__', 'Callback error on beforeDelete');
                return false;
            }

            $kv = array();
            if (isset($this->disabled)) { $kv[0]= 'disabled'; $kv[1] = '1'; }
            else if (isset($this->disabled_at)) { $kv[0] = 'disabled_at'; $kv[1] = date('Y-m-d H:i:s'); }
            else if (isset($this->deleted_at)) { $kv[0] = 'deleted_at'; $kv[1] = date('Y-m-d H:i:s'); }
            if (empty($kv))
            {
                $this->failTrans();
                $this->addError('__disable__', 'Non-supported disable method');
                return false;
            }

            $result = $this->db()->exec(sprintf('UPDATE `%s` SET `%s` = "%s" WHERE id = %d',
                                        $this::$_table, $kv[0], $kv[1], $this->id));

            if (!$result)
            {
                $this->failTrans();
                $this->addError('__disable__', 'Error occerd on disable');
                return false;
            }

            if (!$this->afterDisable())
            {
                $this->failTrans();
                $this->addError('__disable__', 'Callback error on afterDelete');
                return false;
            }

            $this->{$kv[0]} = $kv[1];
            \ORM::clear_cache($this::$_table);
            return $this->completeTrans();
        }
        catch (\Exception $e)
        {
            $this->failTrans();
            $this->addError('__disable__', $e->getMessage());
            return false;
        }
    }

    /**
     * notify
     *
     *   easy implementation for event observer.
     *
     *   for examples:
     *
     *     // User Model
     *     protected function afterSave()
     *     {
     *         // do something.
     *         $result = $this->notifyObserver('save', array('Group', 'Organizaion'));
     *         // do something.
     *     }
     *
     *     // Group Model
     *     protected static function onUserSave(\SlimMVC\Model\User $User)
     *     {
     *         // do something.
     *         return true;
     *     }
     *
     *     // Organization Model
     *     protected static function onUserSave(\SlimMVC\Model\User $User)
     *     {
     *         // do something.
     *         return true;
     *     }
     *
     *     Then, the following method will call automatically.
     *
     *          1. User::afterSave()  // it will call automatically when you called Model::save()
     *          2.   - Group::onUserSave()
     *          3.   - Organization::onUserSave()
     *
     *
     * @param string $event
     * @param string||array $models
     * @return bool
     */
    final protected function notify($event, $models)
    {
        if (!is_string($event)) { return false; }
        if (!is_array($models)) { $models = array($models); }

        $_className =  explode('\\', get_class($this));
        $className = array_pop($_className);
        $method = 'on' . $className . \SlimMVC\Util::capitalize($event);

        $result = true;
        foreach ($models as $model)
        {
            if (!is_string($model))
            {
                throw new \Exception('Invalid model name');
            }
            $model = '\\SlimMVC\\Model\\'.\SlimMVC\Util::capitalize($model);
            if (!class_exists($model) || !method_exists($model, $method))
            {
                throw new \Exception(sprintf('Cannot calling method %s::%s', $model, $method));
            }

            $result = $result && $model::$method($this);
        }

        return $result;
    }

    /**
     * Starting transaction.
     *
     * @return bool  return false, if your db unsupported transaction.
     */
    public function startTrans()
    {
        $counter =& $this->getTransactionCounter();

        try
        {
            if ($this->db()->inTransaction())
            {
                if ($counter < 1) { $counter = 1; }
                $counter++;
                return true;
            }
            else if ($this->db()->beginTransaction())
            {
                $counter++;
                return true;
            }
            return false;
        }
        catch (\Exception $e)
        {
            return false;
        }
    }

    /**
     * To commit active transaction, if all transactions has finished.
     * It is support Nested-Transaction.
     *
     * For example:
     *
     *      $user = model('User')->find_one(1);
     *
     *      $user->startTrans();    // <- Begin Transaction
     *      $user->account = "change_account";
     *      $user->save();          // <- Call completeTrans in save method. but it will not commit, because previous transaction has not been commited.
     *      $organization = $user->organization()->find_one();
     *      $organization->name = "change_name";
     *      $organization->save();  // <- Call completeTrans in save method. of course, this is not commited yet.
     *      $user->completeTrans(); // <- Commit Transaction.
     *
     * @return bool
     */
    public function completeTrans()
    {
        $counter =& $this->getTransactionCounter();
        $has_failure =& $this->getTransactionFailure();

        if ($counter < 1) { return false; }
        try
        {
            $counter--;
            if (0 < $counter) { return !$has_failure; }
            $has_failure = false;
            $counter = 0;
            return $this->db()->commit();
        }
        catch (\Exception $e)
        {
            return false;
        }
    }

    /**
     * To rollback active transaction, if all transaction has finished.
     *
     * @return bool
     */
    public function failTrans()
    {
        $counter =& $this->getTransactionCounter();
        $has_failure =& $this->getTransactionFailure();

        if ($counter < 1) { return false; }
        try
        {
            $counter--;
            $has_failure = true;
            if (0 < $counter) { return true; }
            $has_failure = false;
            $counter = 0;
            return $this->db()->rollback();
        }
        catch (\Exception $e)
        {
            return false;
        }
    }

    /**
     * Return reference of transaction counter
     *
     * @return &int
     */
    final protected function &getTransactionCounter()
    {
        $con = $this->getConnectionName();
        if (!is_array(self::$__trans_counter)) { self::$__trans_counter = array(); }
        if (!isset(self::$__trans_counter[$con])) { self::$__trans_counter[$con] = 0; }
        return self::$__trans_counter[$con];
    }

    /**
     * Return reference of transaction failure
     *
     * @return &bool
     */
    final protected function &getTransactionFailure()
    {
        $con = $this->getConnectionName();
        if (!is_array(self::$__trans_has_failure)) { self::$__trans_has_failure = array(); }
        if (!isset(self::$__trans_has_failure[$con])) { self::$__trans_has_failure[$con] = false; }
        return self::$__trans_has_failure[$con];
    }

    /**
     * Return connection name of current instance
     *
     * @return string
     */
    final protected function getConnectionName()
    {
        $className = get_class($this);
        return $className::$_connection_name;
    }

    /**
     * Return PDO object
     *
     * @return PDO
     */
    final public function db()
    {
        return \ORM::get_db($this->getConnectionName());
    }

    /**
     * loadPlugin
     *
     * @return void
     */
    final protected function loadPlugin()
    {
        if (is_null($this->_plugin))
        {
            $cnames = explode('\\', get_class($this));
            $_class = array_pop($cnames);
            $class_name = '\\SlimMVC\\Plugin\\Model\\' . $_class;
            $class_file = APP_PLUGIN_DIR.DS.'Model'.DS.$_class.'.php';

            if (is_file($class_file) && class_exists($class_name))
            {
                $this->_plugin = new $class_name($this);
            }
        }
    }

    /**
     * callPlugin
     *
     * @param string $method
     * @return bool
     */
    final protected function callPlugin($method)
    {
        if (is_null($this->_plugin)) { return true; }
        if (!is_string($method)) { return true; }
        if (!method_exists($this->_plugin, $method)) { return true; }

        return $this->_plugin->$method();
    }

    // **************************************************************************************
    //   The following, these callback methods are invoked automatically.
    //   You can override these methods to control the behavior CRUD methods.
    // **************************************************************************************
    protected function beforeValidation(){ return $this->callPlugin('beforeValidation'); }
    protected function afterValidation(){ return $this->callPlugin('afterValidation'); }
    protected function beforeValidationOnCreate(){ return $this->callPlugin('beforeValidationOnCreate'); }
    protected function afterValidationOnCreate(){ return $this->callPlugin('afterValidationOnCreate'); }
    protected function beforeValidationOnUpdate(){ return $this->callPlugin('beforeValidationOnUpdate'); }
    protected function afterValidationOnUpdate(){ return $this->callPlugin('afterValidationOnUpdate'); }
    protected function beforeSave(){ return $this->callPlugin('beforeSave'); }
    protected function afterSave(){ return $this->callPlugin('afterSave'); }
    protected function beforeCreate(){ return $this->callPlugin('beforeCreate'); }
    protected function afterCreate(){ return $this->callPlugin('afterCreate'); }
    protected function beforeUpdate(){ return $this->callPlugin('beforeUpdate'); }
    protected function afterUpdate(){ return $this->callPlugin('afterUpdate'); }
    protected function beforeDelete(){ return $this->callPlugin('beforeDelete'); }
    protected function afterDelete(){ return $this->callPlugin('afterDelete'); }
    protected function beforeDisable(){ return $this->callPlugin('beforeDisable'); }
    protected function afterDisable(){ return $this->callPlugin('afterDisable'); }
    public function validate(){ /* do nothig here. override at implemented class  */  }
    public function validateOnCreate(){ /* do nothig here. override at implemented class  */ }
    public function validateOnUpdate(){ /* do nothig here. override at implemeted class  */ }

    // **************************************************************************************
    //    The following, validation methods.
    // **************************************************************************************
    /**
     * To validate the presence of value.
     *
     * it is an error the following:
     *   - null
     *   - undefined key
     *   - empty-string
     *   - empty-array
     *
     * @param string||array $keys
     * @param string $message
     * @return void
     */
    final protected function validatesPresenceOf($keys, $message = '%s field is required')
    {
        if (!is_array($keys)) { $keys = array($keys); }
        foreach ($keys as $key)
        {
            if (!isset($this->{$key}) || is_null($this->{$key})
                || (is_string($this->{$key}) && trim($this->{$key}) === "")
                || (is_array($this->{$key}) && empty($this->{$key})))
            {
                $this->addError($key, sprintf($message, $key));
            }
        }
    }

    /**
     * To validate the range of value.
     *
     * it is an error the following:
     *   - int value out of range
     *   - string length  out of range
     *   - array cout out of range
     *
     *   Example:
     *      // $this->account = "account_name"; $this->groups = array(1,2,3,4);
     *      $this->validatesLengthOf('account', 2, 8);         // <- OK: string length should be 2-8.
     *      $this->validatesLengthOf('groups', null, 3);       // <- NG: array count should be 3 lower.
     *
     * @param string||array $keys
     * @param int $min  ignored if the null
     * @param int $max  ignored if the null
     * @param string $message
     * @return void
     */
    final protected function validatesLengthOf($keys, $min = null, $max = null, $message = '%s field incorrect size')
    {
        if (func_num_args() == 2 && is_string($min)) { $message = $min; $min = null; }
        if (func_num_args() == 3 && is_string($max)) { $message = $max; $max = null; }

        if (!is_array($keys)) { $keys = array($keys); }
        foreach ($keys as $key)
        {
            $is_set = isset($this->{$key});
            $length = 0;
            if (!$is_set || is_null($this->{$key}) || (empty($this->{$key}) && $this->{$key} !== 0)) { continue; }

            if (is_numeric($this->{$key}) && intval($this->{$key}) === $this->{$key}) { $length = $this->{$key}; }
            if (is_string($this->{$key})) { $length = mb_strlen($this->{$key}); }
            if (is_array($this->{$key})) { $length = conut($this->{$key}); }

            if ((!is_null($min) && ($length < $min)) || (!is_null($max) && ($max < $length)))
            {
                $this->addError($key, sprintf($message, $key));
            }
        }
    }

    /**
     * Alias of validatedLengthOf
     *
     * @param string||array $keys
     * @param int $min
     * @param int $max
     * @param string $message
     * @return void
     * @see validatesLengthOf
     */
    final protected function validatesSizeOf($keys, $min = null, $max = null, $message = '%s field incorrect size')
    {
        if (func_num_args() == 2 && is_string($min)) { $message = $min; $min = null; }
        if (func_num_args() == 3 && is_string($max)) { $message = $max; $max = null; }
        $this->validatesLengthOf($keys, $min, $max, $message);
    }

    /**
     * To validate the value with a regular expression pattern.
     *
     * @param string||array $keys
     * @param string $regex Regex pattern
     * @param string $message
     * @return void
     */
    final protected function validatesFormatOf($keys, $regex, $message = '%s field invalid format')
    {
        if (!is_array($keys)) { $keys = array($keys); }
        foreach ($keys as $key)
        {
            if (!isset($this->{$key}) || is_null($this->{$key}) || (empty($this->{$key}) && $this->{$key} !== 0)) { continue; }
            if (!preg_match($regex, (string)($this->{$key})))
            {
                $this->addError($key, sprintf($message, $key));
            }
        }
    }

    /**
     * To validate uniqueness of the value.
     *
     * @param string||array $keys  if it is type of array, used as a multiple-unique key.
     * @param string $message
     * @return void
     */
    final protected function validatesUniquenessOf($keys, $message = '%s field is duplicated')
    {
        if (is_string($keys)) $keys = array($keys);
        if (!is_array($keys)) return;

        $orm = self::factory(get_class($this));
        $id_col = $orm->_get_id_column_name();
        $has_cond = false;
        $is_dirty = false;
        foreach ($keys as $key)
        {
            if (!isset($this->{$key}) || is_null($this->{$key}) || (empty($this->{$key}) && $this->{$key} !== 0)) { continue; }
            $is_dirty = $is_dirty && $this->is_dirty($key);
            $has_cond = true;
            $orm = $orm->where($key, $this->{$key});
        }
        if (!$is_dirty && !$has_cond) return;

        if (!$this->is_new())
        {
            $orm = $orm->whereNotEqual($id_col, $this->{$id_col});
        }

        if (0 < $orm->count())
        {
            $this->addError($key, sprintf($message, join(',', $keys)));
        }
    }

    /**
     * To validate numericality of the value
     *
     * @param string||array $keys
     * @param int $min  it is ignored if the null
     * @param int $max  it is ignored if the null
     * @param string $message
     * @return void
     */
    final protected function validatesNumericalityOf($keys, $min = null, $max = null, $message = '%s fields is not a correct numerical value')
    {
        if (func_num_args() == 2 && is_string($min)) { $message = $min; $min = null; }
        if (func_num_args() == 3 && is_string($max)) { $message = $max; $max = null; }

        if (!is_array($keys)) { $keys = array($keys); }
        foreach ($keys as $key)
        {
            if (!isset($this->{$key}) || is_null($this->{$key}) || (empty($this->{$key}) && $this->{$key} !== 0)) { continue; }
            if (!is_numeric($this->{$key})
                || (!is_null($min) && min($this->{$key}, $min) != $min)
                || (!is_null($max) && max($this->{$key}, $max) != $max))
            {
                $this->addError($key, sprintf($message, $key));
            }
        }
    }

    /**
     * To validate match with the value and the confirm-value.
     *
     * Example:
     *
     *    // $this->password = '1234'; $this->password_confirm = '1234';
     *    $this->validatesConfirmationOf('password');  // <- it is OK.
     *    // $this->password = '1234'; $this->password_confirm = 'abc';
     *    $this->validatesConfirmationOf('password');  // <- it is NG.
     *
     * @param string||array $keys
     * @param string $message
     * @return void
     */
    final protected function validatesConfirmationOf($keys, $message = '%s field do not match confirmation value')
    {
        if (!is_array($keys)) { $keys = array($keys); }
        foreach ($keys as $key)
        {
            $confirmation_key = $key . '_confirm';
            if (!isset($this->{$key})) { continue; }
            if (!$this->is_dirty($key)) { continue; }

            if (!isset($this->{$key}, $this->{$confirmation_key})
                || $this->{$key} !== $this->{$confirmation_key})
            {
                $this->addError($key, sprintf($message, $key));
            }
        }
    }
}

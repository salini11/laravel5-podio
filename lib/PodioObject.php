<?php

class PodioObject
{
    private $__attributes = array();
    private $__belongs_to;
    private $__properties = array();
    private $__relationships = array();
    protected $__id_column;

    protected $podio;

    public function __construct($podio)
    {
        $this->podio = $podio;
    }


    public function init($default_attributes = array())
    {
        if (is_int($default_attributes)) {
            $default_attributes = array('id' => $default_attributes);
        }
        if (is_string($default_attributes)) {
            $default_attributes = array('external_id' => $default_attributes);
        }
        if (!is_array($default_attributes)) {
            $default_attributes = array();
        }

        $has_api_values = !empty($default_attributes['__api_values']);

        // Create object instance from attributes
        foreach ($this->__properties as $name => $property) {
            if (isset($property['options']['id'])) {
                $this->__id_column = $name;
                if (array_key_exists('id', $default_attributes)) {
                    $this->id = $default_attributes['id'];
                }
            }
            if (array_key_exists($name, $default_attributes)) {

                // Special handling for PodioItemField values property so
                // we can construct using both the API format when receiving responses
                // and the much simpler podio-php format when constructing manually.
                if ($name == 'values' && !$has_api_values) {
                    $this->values = $default_attributes[$name];
                } else {
                    $this->set_attribute($name, $default_attributes[$name]);
                }
            }
        }
        if ($this->__relationships) {
            foreach ($this->__relationships as $name => $type) {
                if (array_key_exists($name, $default_attributes)) {
                    $property = $this->__properties[$name];
                    $class_name = 'Podio' . $property['type'];

                    if ($type == 'has_one') {
                        $child = is_object($default_attributes[$name]) ? $default_attributes[$name] : new $class_name($default_attributes[$name]);
                        $child->add_relationship($this, $name);
                        $this->set_attribute($name, $child);
                    } elseif ($type == 'has_many' && is_array($default_attributes[$name])) {

                        // Special handling for ItemField and AppField.
                        // We need to create collection of the right type
                        if ($class_name == 'PodioItemField') {
                            $values = $default_attributes[$name];
                            // Make sure we pass along info on whether the values property
                            // contains API style values or not
                            $collection = new PodioItemFieldCollection($values, $has_api_values);
                        } elseif ($class_name == 'PodioAppField') {
                            $values = $default_attributes[$name];
                            $collection = new PodioAppFieldCollection($values);
                        } else {
                            $values = array();
                            foreach ($default_attributes[$name] as $value) {
                                $child = is_object($value) ? $value : new $class_name($value);
                                $values[] = $child;
                            }
                            $collection = new PodioCollection($values);
                        }
                        $collection->add_relationship($this, $name);
                        $this->set_attribute($name, $collection);
                    }
                }
            }
        }
    }

    public function __set($name, $value)
    {
        if ($name == 'id' && !empty($this->__id_column)) {
            return $this->set_attribute($this->__id_column, $value);
        }
        return $this->set_attribute($name, $value);
    }

    public function __get($name)
    {
        if ($name == 'id' && !empty($this->__id_column)) {
            return empty($this->__attributes[$this->__id_column]) ? null : $this->__attributes[$this->__id_column];
        }
        if ($this->has_attribute($name)) {
            // Create DateTime object if necessary
            if ($this->has_property($name) && ($this->__properties[$name]['type'] == 'datetime' || $this->__properties[$name]['type'] == 'date')) {
                $tz = new DateTimeZone('UTC');
                return DateTime::createFromFormat($this->date_format_for_property($name), $this->__attributes[$name], $tz);
            }

            return $this->__attributes[$name];
        }

        return null;
    }

    public function __isset($name)
    {
        return isset($this->__attributes[$name]);
    }

    public function __unset($name)
    {
        unset($this->__attributes[$name]);
    }

    public function __toString()
    {
        return print_r($this->as_json(false), true);
    }

    public function date_format_for_property($name)
    {
        if ($this->has_property($name)) {
            if ($this->__properties[$name]['type'] == 'datetime') {
                return 'Y-m-d H:i:s';
            } elseif ($this->__properties[$name]['type'] == 'date') {
                return 'Y-m-d';
            }
        }

        return null;
    }

    public function relationship()
    {
        return $this->__belongs_to;
    }

    public function add_relationship($instance, $property = 'fields')
    {
        $this->__belongs_to = array('property' => $property, 'instance' => $instance);
    }

    protected function set_attribute($name, $value)
    {
        if ($this->has_property($name)) {
            $property = $this->__properties[$name];
            switch ($property['type']) {
                case 'integer':
                    $this->__attributes[$name] = $value ? (int)$value : null;
                    break;
                case 'boolean':
                    $this->__attributes[$name] = null;
                    if ($value === true || $value === false) {
                        $this->__attributes[$name] = $value;
                    } elseif ($value) {
                        $this->__attributes[$name] = in_array(trim(strtolower($value)), array('true', 1, 'yes'));
                    }
                    break;
                case 'datetime':
                case 'date':
                    if (is_a($value, 'DateTime')) {
                        $this->__attributes[$name] = $value->format($this->date_format_for_property($name));
                    } else {
                        $this->__attributes[$name] = $value;
                    }
                    break;
                case 'string':
                    if (is_array($value)) {
                        $value = join(', ', $value);
                    }

                    $this->__attributes[$name] = $value ? (string)$value : null;
                    break;
                case 'array':
                case 'hash':
                    $this->__attributes[$name] = $value ? (array)$value : array();
                    break;
                default:
                    $this->__attributes[$name] = $value;
            }
            return true;
        }
        throw new PodioDataIntegrityError("Attribute cannot be assigned. Property '{$name}' doesn't exist.");
    }

    public function listing($response_or_attributes)
    {
        if ($response_or_attributes) {
            if (is_object($response_or_attributes) && get_class($response_or_attributes) == 'PodioResponse') {
                $body = $response_or_attributes->json_body();
            } else {
                $body = $response_or_attributes;
            }
            $list = array();
            foreach ($body as $attributes) {
                $list[] = array_merge($attributes, array('__api_values' => true));
            }
            return $list;
        }
        return null;
    }

    public function member($response)
    {
        if ($response) {
            return array_merge($response->json_body(), array('__api_values' => true));
        }
        return null;
    }

    public function collection($response, $collection_type = "PodioCollection")
    {
        if ($response) {
            $body = $response->json_body();
            $list = array();
            if (isset($body['items'])) {
                foreach ($body['items'] as $attributes) {
                    $list[] = array_merge($attributes, array('__api_values' => true));
                }
            }
            return new $collection_type($list, $body['filtered'], $body['total']);
        }

        return null;
    }

    public function can($right)
    {
        if ($this->has_property('rights')) {
            return $this->has_attribute('rights') && in_array($right, $this->rights);
        }
        return false;
    }

    public function has_attribute($name)
    {
        return array_key_exists($name, $this->__attributes);
    }

    public function has_property($name)
    {
        return array_key_exists($name, $this->__properties);
    }

    public function has_relationship($name)
    {
        return array_key_exists($name, $this->__relationships);
    }

    public function properties()
    {
        return $this->__properties;
    }

    public function relationships()
    {
        return $this->__relationships;
    }

    /**
     * Raw access to attributes. Only used for unit testing. Do not use.
     */
    public function __attribute($name)
    {
        return $this->__attributes[$name];
    }

    // Define a property on this object
    public function property($name, $type, $options = array())
    {
        if (!$this->has_property($name)) {
            $this->__properties[$name] = array('type' => $type, 'options' => $options);
        }
    }

    public function has_one($name, $class_name, $options = array())
    {
        $this->property($name, $class_name, $options);
        if (!$this->has_relationship($name)) {
            $this->__relationships[$name] = 'has_one';
        }
    }

    public function has_many($name, $class_name, $options = array())
    {
        $this->property($name, $class_name, $options);
        if (!$this->has_relationship($name)) {
            $this->__relationships[$name] = 'has_many';
        }
    }

    public function as_json()
    {
        // Made it like this to maintain old code stuff...

        return json_encode($this);
    }

}

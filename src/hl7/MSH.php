<?php


namespace Uhin\HL7;




use JsonSerializable;

class MSH Extends Segment implements JsonSerializable
{
    public $properties;
    private $separators;

    public function __construct($segment,$separators)
    {
        $this->properties = new \stdClass();
        $this->separators = $separators;
        if(!is_array($segment))
        {
            $segment = explode($this->separators->segment_separator,$segment);
        }

        if(count($segment) > 0)
        {
            for ($i=0; $i < count($segment); $i++)
            {
                /* First time through create an empty object so we don't get php warnings. */
                if($i == 0)
                {
//                    $this->properties->{$segment[0].'.'.$i} = '';
                    $this->properties->{$segment[0].'.'.($i+1)} = $this->separators->segment_separator;
                }
                if($i > 0)
                {
                    $value = $segment[$i];

                    if($value == "" || $i == 1)
                    {
                        $this->properties->{$segment[0].'.'.($i+1)} = $value;
                    }
                    else
                    {
                        $this->properties->{$segment[0].'.'.($i+1)} = new Field($value,$segment[0].'.'.($i+1),$separators);
                    }
                }
            }
        }
    }
}
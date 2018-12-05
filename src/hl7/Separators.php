<?php


namespace Uhin\HL7;


class Separators
{

    public $segment_separator = '|';
    public $component_separator = '^';
    public $repetition_separator = '~';
    public $escape_character = '\\';
    public $subcomponent_separator = '&';
    /**
     * Sets the control characters from the stored text property.
     */
    public function __construct()
    {

    }

    public function setControlCharacters($hl7)
    {
        /* Make sure the text is long enough to avoid PHP exceptions. */
        if(strlen($hl7) < 7)
        {
            throw new \Exception("Unable to parse HL7 messages, does not contain separation characters definition.");
        }

        $this->segment_separator = substr($hl7,3,1);
        $this->component_separator = substr($hl7,4,1);
        $this->repetition_separator = substr($hl7,5,1);
        $this->escape_character = substr($hl7,6,1);
        $this->subcomponent_separator = substr($hl7,7,1);

//        if($this->escape_character == '\\')
//        {
//            $this->escape_character = '\\\\';
//        }
    }
}
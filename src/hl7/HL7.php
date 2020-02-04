<?php

namespace Uhin\HL7;



use JsonSerializable;


class HL7 implements JsonSerializable
{

    public $properties;
    private $text;
    private $separators;

    public function __construct()
    {
        $this->properties = [];
        $this->text = '';
        $this->separators = new Separators();
    }

    public function __get($name)
    {
        switch ($name) {
            case "first_name":
                return $this->getFirstName();
            case "middle_name":
                return $this->getMiddleName();
            case "last_name":
                return $this->getLastName();
            case "message_type":
                return $this->getMessageType();
            case "patient_type":
                return $this->getPatientType();
            case "control_number":
                return $this->getControlNumber();
            case "event_time":
                return $this->getEventTime();
            case "facility_name":
                return $this->getFacilityName();
            case "eid":
                return $this->getIdentifier("UHIN");
            case "gender":
                return $this->getGender();
            case "date_of_birth":
                return $this->getDOB();
            case "address":
                return $this->getAddress();
            case "phone":
                return $this->getPhones();
            case "admit_info":
                return $this->getAdmitInfo();
            case "discharge_info":
                return $this->getDischargeInfo();
            case "patient_complaint":
                return $this->getPatientComplaint();
            case "death_info":
                return $this->getDeathInfo();
            case "diagnosis_info":
                return $this->getDiagnosisInfo();
            case "hospital_service":
                return $this->getHospitalService();
            case "race":
                return $this->getRace();
            case "ethnicity":
                return $this->getEthnicity();
            case "patient_account_number":
                return $this->getPatientAccountNumber();
            case "assigned_patient_location":
                return $this->getAssignedPatientLocation();
            case "provider":
                return $this->getProvider();
            default:
                if (array_key_exists($name, $this->properties)) {
                    return $this->properties[$name];
                } else {
                    return null;
                }
                break;
        }
    }

    public function __set($name, $value)
    {

    }

    public function parse($hl7)
    {
        /* Check that the HL7 starts with a MSH */
        if (substr($hl7, 0, 3) != "MSH") {
            throw new \Exception("Failed to parse an HL7 message from the supplied data. Supplied data does not start with MSH.");
        }
        /* Normalize the input */
        $this->text = self::normalizeLineEndings($hl7);

        /* determine control characters. */
        $this->separators->setControlCharacters($this->text);

        try {
            $segments = HL7::explode(PHP_EOL, $this->text, $this->separators->escape_character);

            foreach ($segments as $value) {
                $row = HL7::explode($this->separators->segment_separator, $value, $this->separators->escape_character);

                $key = $row[0]; // $this->generateKey($row);

                /* Check to see if we need to turn the property into an array for duplicate key situations.  */
                if (array_key_exists($key, $this->properties)) {
                    if (!is_array($this->properties[$key])) {
                        $temp = $this->properties[$key];
                        $this->properties[$key] = [];
                        $this->properties[$key][] = $temp;
                    }
                }

                if ($key != "") {
                    switch ($key) {
                        case "MSH":
                            $this->properties[$key] = new MSH($row, $this->separators);
                            break;
                        /* Repeatable segments go here. Still working on a definitive list. */
                        case "NK1":
                        case "DG1":
                        case "OBX":
                        case "PR1":
                        case "NTE":
                        case "AL1":
                        case "ACC":
                        case "IAM":
                        case "GT1":
                        case "ROL":
                        case "IN1":
                        case "IN2":
                        case "IN3":
                        case "PID":
                        case "PD1":
                        case "PV2":
                        case "CON":
                            $this->properties[$key][] = new Segment($row, $this->separators);
                            break;
                        default:
                            if (strpos($key, 'Z') === 0) {
                                $this->properties[$key][] = new Segment($row, $this->separators);
                            } else {
                                if (array_key_exists($key, $this->properties)) {
                                    throw new \Exception("Repeatable Segment found outside of an array. " . $key);
                                }
                                $this->properties[$key] = new Segment($row, $this->separators);
                            }
                            break;
                    }
                }
            }
        } catch (\Exception $e) {
            throw new \Exception("Error spliting rows. " . $e->getMessage());
        }

    }

    public function glue()
    {
        $hl7 = "";
        /* Make sure that MSH is the first property in the list */
//        if(count($this->properties) > 0)
//        {
//            if(!(is_a($this->properties[0], 'MSH')))
//            {
//                throw new \Exception("First element is not a MSH, I cannot proceed.");
//            }
//        }

        foreach ($this->properties as $key => $property) {
            if (is_array($property)) {
                foreach ($property as $segment) {
                    $hl7 = $hl7 . $key . $property->glue . "\r\n";
                }
            } else {
                $hl7 = $hl7 . $key . $property->glue() . "\r\n";
            }

        }

    }

    public function jsonSerialize()
    {
        return $this->properties;
    }

    /**
     * @param $text
     * @return mixed
     */
    public static function normalizeLineEndings($text)
    {
        $text = str_replace("\\.br\\", ".br.",$text);
        $text = str_replace("\r\n", PHP_EOL, $text);

        $text = str_replace("\r", PHP_EOL, $text);

        $text = str_replace("\n", PHP_EOL, $text);

        return $text;
    }

    public static function needsBreakdown($input, Separators $separators)
    {
        if (HL7::containsComponentSeparator($input, $separators)) {
            return true;
        }

        if (HL7::containsRepetitionSeparator($input, $separators)) {
            return true;
        }

        if (HL7::containsSubcomponentSeparator($input, $separators)) {
            return true;
        }
        return false;
    }

    public static function containsComponentSeparator($input, Separators $separators)
    {
        if (HL7::str_contains($separators->component_separator, $input, $separators->escape_character)) {
            return true;
        } else {
            return false;
        }
//        if(preg_match('/(?<!\\'.$separators->escape_character.'.)\\'.$separators->component_separator.'/',$input))
//        {
//            return true;
//        }
//        else
//        {
//            return false;
//        }
    }

    public static function containsRepetitionSeparator($input, Separators $separators)
    {
        if (HL7::str_contains($separators->repetition_separator, $input, $separators->escape_character)) {
            return true;
        } else {
            return false;
        }
//        if(preg_match('/(?<!\\'.$separators->escape_character.'.)\\'.$separators->repetition_separator.'/',$input))
//        {
//            return true;
//        }
//        else
//        {
//            return false;
//        }
    }

    public static function containsSubcomponentSeparator($input, Separators $separators)
    {
        if (HL7::str_contains($separators->subcomponent_separator, $input, $separators->escape_character)) {
            return true;
        } else {
            return false;
        }
//        if(preg_match('/(?<!\\'.$separators->escape_character.'.)\\'.$separators->subcomponent_separator.'/',$input))
//        {
//            return true;
//        }
//        else
//        {
//            return false;
//        }
    }

    /**
     * @param $search
     * @param $subject
     * @param $escapeCharacter
     * @return bool
     */
    public static function str_contains($search, $subject, $escapeCharacter)
    {
        $str_array = str_split($subject);
        for ($i = 0; $i < count($str_array); $i++) {
            if ($str_array[$i] == $search) {
                /* Look for escape character */
                if ($i > 0 && ($str_array[$i - 1] == $escapeCharacter)) {
                    continue;
                } else {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @param $name
     * @return bool
     */
    public function __isset($name)
    {
        return !is_null($this->__get($name));
    }

    /**
     * @param $delimiter
     * @param $str
     * @param string $escapeChar
     * @return array
     * Stole this code from https://r.je/php-explode-split-with-escape-character.html
     */
    public static function explode($delimiter, $str, $escapeChar = '\\')
    {

        //Just some random placeholders that won't ever appear in the source $str

        $double = "\0\0\0_doub";

        $escaped = "\0\0\0_esc";

        $str = str_replace($escapeChar . $escapeChar, $double, $str);

        $str = str_replace($escapeChar . $delimiter, $escaped, $str);

        $split = explode($delimiter, $str);

        if ($escapeChar = '\\') {
            $escapeChar = '\\';
        }

        foreach ($split as &$val) $val = str_replace([$double, $escaped], [$escapeChar . $escapeChar, $escapeChar . $delimiter], $val);

        return $split;

    }

    /**
     * @return null
     */
    private function getFirstName()
    {
        if (is_array($this->PID[0]->{'PID.5'})) {
            if (isset($this->PID[0]->{'PID.5'}[0]->{'PID.5.2'})) {
                return $this->PID[0]->{'PID.5'}[0]->{'PID.5.2'};
            }
        } else {
            if (isset($this->PID[0]->{'PID.5'}->{'PID.5.2'})) {
                return $this->PID[0]->{'PID.5'}->{'PID.5.2'};
            }
        }
        return null;
    }


    /**
     * @return null
     */
    private function getMiddleName()
    {
        if (is_array($this->PID[0]->{'PID.5'})) {
            if (isset($this->PID[0]->{'PID.5'}[0]->{'PID.5.3'})) {
                return $this->PID[0]->{'PID.5'}[0]->{'PID.5.3'};
            }
        } else {
            if (isset($this->PID[0]->{'PID.5'}->{'PID.5.3'})) {
                return $this->PID[0]->{'PID.5'}->{'PID.5.3'};
            }
        }
        return null;
    }

    /**
     * @return null
     */
    private function getLastName()
    {
        if (is_array($this->PID[0]->{'PID.5'})) {
            if (isset($this->PID[0]->{'PID.5'}[0]->{'PID.5.1'})) {
                return $this->PID[0]->{'PID.5'}[0]->{'PID.5.1'};
            }
        } else {
            if (isset($this->PID[0]->{'PID.5'}->{'PID.5.1'})) {
                return $this->PID[0]->{'PID.5'}->{'PID.5.1'};
            }
        }
        return null;
    }

    /**
     * @return null
     */
    private function getFacilityName()
    {
        if (isset($this->MSH->{'MSH.4'}->{'MSH.4.1'})) {
            return $this->MSH->{'MSH.4'}->{'MSH.4.1'};
        }
        return null;
    }

    /**
     * @return string|null
     */
    private function getMessageType()
    {
        if (isset($this->MSH->{'MSH.9'}->{'MSH.9.2'})) {
            return $this->MSH->{'MSH.9'}->{'MSH.9.2'};
        }
        return null;
    }



    /**
     * @return bool|string|null
     */
    private function getPatientType()
    {
        if (isset($this->PV1->{'PV1.2'}->{'PV1.2.1'})) {
            return substr($this->PV1->{'PV1.2'}->{'PV1.2.1'}, 0, 10);
        }
        return null;
    }

    /**
     * @return string|null
     */
    private function getControlNumber()
    {
        if (isset($this->MSH->{'MSH.10'}->{'MSH.10.1'})) {
            return $this->MSH->{'MSH.10'}->{'MSH.10.1'};
        }
        return null;
    }

    /**
     * @return string|null
     */
    private function getEventTime()
    {
        if (!isset($this->EVN) || !isset($this->EVN->{'EVN.2'}->{'EVN.2.1'})) {
            if (isset($this->MSH->{'MSH.7'}->{'MSH.7.1'})) {
                return $this->parseDate($this->MSH->{'MSH.7'}->{'MSH.7.1'});
            }
        } else if (isset($this->EVN->{'EVN.2'}->{'EVN.2.1'})) {
            return $this->parseDate($this->EVN->{'EVN.2'}->{'EVN.2.1'});
        }

        return null;
    }

    /**
     * @param $input
     * @return string|null
     */
    private function parseDate($input)
    {
        /* Try standard date formats */
        try {
            $time = new \DateTime($input);
            return $time->format("Y-m-d H:i:s");
        } catch (\Exception $e) {
            $e->getMessage();
        }

        /* Try some know custom date formats */
        try {
            $time = \DateTime::createFromFormat('YmdHis.vO', $input);
            return $time->format("Y-m-d H:i:s");
        } catch (\Exception $e) {
            $e->getMessage();
        }
        return null;
    }

    /**
     * @param $source
     * @return string|null
     */
    public function getIdentifier($source)
    {
        if (!isset($this->PID)) {
            return null;
        }

        /* Try PID.2.1 First */
        if (isset($this->PID[0]->{"PID.2"})) {
            if (is_array($this->PID[0]->{"PID.2"})) {
                foreach ($this->PID[0]->{"PID.2"} as $PID) {
                    if (isset($PID->{"PID.2.4"})) {
                        if (is_object($PID->{"PID.2.4"})) {
                            if (isset($PID->{"PID.2.4"}->{"PID.2.4.1"})) {
                                if (strpos($PID->{"PID.2.4"}->{"PID.2.4.1"}, $source) !== false) {
                                    if (isset($PID->{"PID.2.1"})) {
                                        return $PID->{"PID.2.1"};
                                    }
                                }
                            }
                        } else if (strpos($PID->{"PID.2.4"}, $source) !== false) {
                            if (isset($PID->{"PID.2.1"})) {
                                return $PID->{"PID.2.1"};
                            }
                        }
                    }
                }
            } else {
                if (isset($this->PID[0]->{"PID.2"}->{"PID.2.4"}) && strpos($this->PID[0]->{"PID.2"}->{"PID.2.4"}, $source) !== false) {
                    if (isset($this->PID[0]->{"PID.2"}->{"PID.2.1"})) {
                        return $this->PID[0]->{"PID.2"}->{"PID.2.1"};
                    }
                }
            }
        }

        /* Try the PID.3.1 Next */
        if (isset($this->PID[0]->{"PID.3"})) {
            if (is_array($this->PID[0]->{"PID.3"})) {
                foreach ($this->PID[0]->{"PID.3"} as $PID) {
                    if (isset($PID->{"PID.3.4"})) {
                        if (is_object($PID->{"PID.3.4"})) {
                            if (isset($PID->{"PID.3.4"}->{"PID.3.4.1"})) {
                                if (strpos($PID->{"PID.3.4"}->{"PID.3.4.1"}, $source) !== false) {
                                    if (isset($PID->{"PID.3.1"})) {
                                        return $PID->{"PID.3.1"};
                                    }
                                }
                            }
                        } else if (strpos($PID->{"PID.3.4"}, $source) !== false) {
                            if (isset($PID->{"PID.3.1"})) {
                                return $PID->{"PID.3.1"};
                            }
                        }
                    }
                }
            } else {
                if (isset($this->PID[0]->{"PID.3"}->{"PID.3.4"})) {
                    if (is_object($this->PID[0]->{"PID.3"}->{"PID.3.4"})) {
                        if (isset($this->PID[0]->{"PID.3"}->{"PID.3.4"}->{"PID.3.4.1"})) {
                            if (strpos($this->PID[0]->{"PID.3"}->{"PID.3.4"}->{"PID.3.4.1"}, $source) !== false) {
                                if (isset($this->PID[0]->{"PID.3"}->{"PID.3.1"})) {
                                    return $this->PID[0]->{"PID.3"}->{"PID.3.1"};
                                }
                            }
                        }
                    }
                    if (strpos($this->PID[0]->{"PID.3"}->{"PID.3.4"}, $source) !== false) {
                        if (isset($this->PID[0]->{"PID.3"}->{"PID.3.1"})) {
                            return $this->PID[0]->{"PID.3"}->{"PID.3.1"};
                        }
                    }
                }
            }
        }
        return null;
    }

    /**
     * @return string|null
     */
    private function getGender()
    {
        if (isset($this->PID[0]->{'PID.8'}->{'PID.8.1'}) && !is_object($this->PID[0]->{'PID.8'}->{'PID.8.1'})) {
            return $this->PID[0]->{'PID.8'}->{'PID.8.1'};
        }
        return null;
    }

    /**
     * @return string
     */
    private function getDOB()
    {
        if(isset($this->PID[0]->{'PID.7'}->{'PID.7.1'})) {
            try {
                $date = new \DateTime($this->PID[0]->{'PID.7'}->{'PID.7.1'});
                return $date->format('m/d/Y');
            } catch (\Exception $e) {
                return $this->PID[0]->{'PID.7'}->{'PID.7.1'};
            }
        }
        else{
            return null;
        }
    }

    /**
     * @return array
     */
    private function getAddress()
    {
        $addresses= [];

        if (isset($this->PID[0]->{'PID.11'}) && is_array($this->PID[0]->{'PID.11'})) {
            foreach ($this->PID[0]->{'PID.11'} as $current)
            {
                $address = $this->extractAddress($current);
                if(!is_null($address))
                {
                    $addresses[] = $address;
                }
            }
        }
        else if(isset($this->PID[0]->{'PID.11'}))
        {
            $address = $this->extractAddress($this->PID[0]->{'PID.11'});
            if(!is_null($address))
            {
                $addresses[] = $address;
            }
        }

        return $addresses;
    }

    /*
     * {
            "area_code":"",
            "country_code":"",
            "ext":"",
            "number":"",
            "type":""
        }
     */
    private function getPhones()
    {
        $phones = [];

        if (isset($this->PID[0]->{'PID.13'}) && is_array($this->PID[0]->{'PID.13'})) {
            foreach ($this->PID[0]->{'PID.13'} as $value) {
                $phone = $this->extractPhone($value, 13);
                if (!is_null($phone)) {
                    $phones[] = $phone;
                }
            }
        } else if (isset($this->PID[0]->{'PID.13'})) {
            $phone = $this->extractPhone($this->PID[0]->{'PID.13'}, 13);
            if (!is_null($phone)) {
                $phones[] = $phone;
            }
        }
        if (isset($this->PID[0]->{'PID.14'})) {
            $phone = $this->extractPhone($this->PID[0]->{'PID.14'}, 14);
            if (!is_null($phone)) {
                $phones[] = $phone;
            }
        }

        return $phones;
    }

    /**
     * @return \stdClass|null
     */
    private function getAdmitInfo()
    {
        $admit = new \stdClass();
        $admit->admit_source = null;
        $admit->admit_time = null;

        if(isset($this->PV1->{'PV1.14'}->{'PV1.14.1'}) && !is_object($this->PV1->{'PV1.14'}->{'PV1.14.1'}))
        {
            $admit->admit_source = $this->normalizeAdmitSource($this->PV1->{'PV1.14'}->{'PV1.14.1'});
        }

        if(isset($this->PV1->{'PV1.44'}->{'PV1.44.1'}) && !is_object($this->PV1->{'PV1.44'}->{'PV1.44.1'}))
        {
            try
            {
                $time = new \DateTime($this->PV1->{'PV1.44'}->{'PV1.44.1'});
                $admit->admit_time = $time->format("Y-m-d H:i:s");
            }
            catch (\Exception $e)
            {
                $admit->admit_time = $this->PV1->{'PV1.44'}->{'PV1.44.1'};
            }
        }

        if(is_null($admit->admit_time) && is_null($admit->admit_source))
        {
            return null;
        }

        return $admit;

    }

    /**
     * @return \stdClass|null
     */
    private function getDischargeInfo()
    {
        $discharge = new \stdClass();
        $discharge->discharge_disposition = null;
        $discharge->discharge_to_location = null;
        $discharge->discharge_time = null;

        if(isset($this->PV1->{'PV1.45'}->{'PV1.45.1'}) && !is_object($this->PV1->{'PV1.45'}->{'PV1.45.1'}))
        {
            try
            {
                $time = new \DateTime($this->PV1->{'PV1.45'}->{'PV1.45.1'});
                $discharge->discharge_time = $time->format("Y-m-d H:i:s"); ;
            }
            catch (\Exception $e)
            {
                $discharge->discharge_time = $this->PV1->{'PV1.45'}->{'PV1.45.1'};
            }
        }

        if(isset($this->PV1->{'PV1.36'}->{'PV1.36.1'}) && !is_object($this->PV1->{'PV1.36'}->{'PV1.36.1'}))
        {
            $discharge->discharge_disposition = $this->PV1->{'PV1.36'}->{'PV1.36.1'};
        }

        if(isset($this->PV1->{'PV1.37'}->{'PV1.37.1'}) && !is_object($this->PV1->{'PV1.37'}->{'PV1.37.1'}))
        {
            $discharge->discharge_to_location = $this->PV1->{'PV1.37'}->{'PV1.37.1'};
        }

        if(is_null($discharge->discharge_disposition) && is_null($discharge->discharge_to_location) && is_null($discharge->discharge_time))
        {
            return null;
        }
        return $discharge;
    }

    /**
     * @return array
     */
    private function getPatientComplaint()
    {
        $complaints = [];

        if(isset($this->PV2))
        {
            foreach ($this->PV2 as $pv2)
            {
                $complaint = new \stdClass();
                $complaint->patient_complaint_code = null;
                $complaint->patient_complaint = null;

                if (isset($pv2->{'PV2.3'}->{'PV2.3.1'}) && !is_object($pv2->{'PV2.3'}->{'PV2.3.1'})) {
                    $complaint->patient_complaint_code = $pv2->{'PV2.3'}->{'PV2.3.1'};
                }

                if (isset($pv2->{'PV2.3'}->{'PV2.3.2'}) && !is_object($pv2->{'PV2.3'}->{'PV2.3.2'})) {
                    $complaint->patient_complaint = $pv2->{'PV2.3'}->{'PV2.3.2'};
                }

                if (!is_null($complaint->patient_complaint_code) || !is_null($complaint->patient_complaint)) {
                    $complaints[] = $complaint;
                }
            }
        }
        return $complaints;
    }


    private function getDeathInfo()
    {
        $death = new \stdClass();
        $death->death_indicator = null;
        $death->date_of_death = null;

        if(isset($this->PID[0]->{'PID.30'}->{'PID.30.1'}) && !is_object($this->PID[0]->{'PID.30'}->{'PID.30.1'}))
        {
            $death->death_indicator = $this->PID[0]->{'PID.30'}->{'PID.30.1'};
        }

        if(isset($this->PID[0]->{'PID.29'}->{'PID.29.1'}) && !is_object($this->PID[0]->{'PID.29'}->{'PID.29.1'}))
        {
            $death->date_of_death = $this->PID[0]->{'PID.29'}->{'PID.29.1'};
            try
            {
                $time = new \DateTime($this->PID[0]->{'PID.29'}->{'PID.29.1'});
                $death->date_of_death  = $time->format("Y-m-d H:i:s"); ;
            }
            catch (\Exception $e)
            {
                $death->date_of_death  = $this->PID[0]->{'PID.29'}->{'PID.29.1'};
            }
        }

        if(is_null($death->death_indicator) && is_null($death->date_of_death))
        {
            return null;
        }

        return $death;
    }

    /**
     * @return array
     */
    private function getDiagnosisInfo()
    {
        $diags = [];
        if(isset($this->DG1))
        {
            foreach ($this->DG1 as $dg)
            {
                $diag = new \stdClass();
                $diag->diagnosis_code = null;
                $diag->diagnosis_description = null;

                if(isset($dg->{'DG1.3'}->{'DG1.3.1'}))
                {
                    $diag->diagnosis_code = $dg->{'DG1.3'}->{'DG1.3.1'};
                }

                if(isset($dg->{'DG1.4'}->{'DG1.4.1'}))
                {
                    $diag->diagnosis_description = $dg->{'DG1.4'}->{'DG1.4.1'};
                }

                if(!is_null($diag->diagnosis_code) || !is_null($diag->diagnosis_description))
                {
                    $diags[] = $diag;
                }
            }
        }

        return $diags;
    }

    /**
     * @return string|null
     */
    private function getHospitalService()
    {
        if(isset($this->PV1->{'PV1.10'}->{'PV1.10.1'}) && !is_object($this->PV1->{'PV1.10'}->{'PV1.10.1'}))
        {
            return $this->PV1->{'PV1.10'}->{'PV1.10.1'};
        }
        return null;
    }

    /**
     * @return string|null
     */
    private function getRace()
    {
        if(isset($this->PID[0]->{'PID.10'}->{'PID.10.1'}) && !is_object($this->PID[0]->{'PID.10'}->{'PID.10.1'}))
        {
            return $this->normalizeRace($this->PID[0]->{'PID.10'}->{'PID.10.1'});
        }
        return null;
    }

    /**
     * @return string|null
     */
    private function getEthnicity()
    {
        if(isset($this->PID[0]->{'PID.22'}->{'PID.22.1'}) && !is_object($this->PID[0]->{'PID.22'}->{'PID.22.1'}))
        {
            return $this->PID[0]->{'PID.22'}->{'PID.22.1'};
        }
        return null;
    }

    /**
     * @return string|null
     */
    private function getPatientAccountNumber()
    {
        if(isset($this->PID[0]->{'PID.18'}->{'PID.18.1'}) && !is_object($this->PID[0]->{'PID.18'}->{'PID.18.1'}))
        {
            return $this->PID[0]->{'PID.18'}->{'PID.18.1'};
        }

        return null;
    }

    /**
     * @return string|null
     */
    private function getAssignedPatientLocation()
    {
        if(isset($this->PV1->{'PV1.3'}->{'PV1.3.1'}) && !is_object($this->PV1->{'PV1.3'}->{'PV1.3.1'}))
        {
            return $this->PV1->{'PV1.3'}->{'PV1.3.1'};
        }
        return null;
    }

    /**
     * @return \stdClass
     */
    private function getProvider()
    {
        /*
        AttendingDoctorFirst - PV1.7.3
        AttendingDoctorMiddle - PV1.7.4
        AttendingDoctorLast - PV1.7.2
        AttendingDoctorNPI - PV1.7.1
        */

        $provider = new \stdClass();
        $provider->first_name = null;
        $provider->middle_name = null;
        $provider->last_name = null;
        $provider->npi = null;

        if (isset($this->PV1->{'PV1.7'}->{'PV1.7.3'})) {
            $provider->first_name = $this->PV1->{'PV1.7'}->{'PV1.7.3'};
        }

        if (isset($this->PV1->{'PV1.7'}->{'PV1.7.4'})) {
            $provider->middle_name = $this->PV1->{'PV1.7'}->{'PV1.7.4'};
        }

        if (isset($this->PV1->{'PV1.7'}->{'PV1.7.2'})) {
            $provider->last_name = $this->PV1->{'PV1.7'}->{'PV1.7.2'};
        }

        if (isset($this->PV1->{'PV1.7'}->{'PV1.7.1'})) {
            $provider->npi = $this->PV1->{'PV1.7'}->{'PV1.7.1'};
        }

        return $provider;

    }

    /**
     * @param $value
     * @param $pid_value
     * @return \stdClass|null
     */
    private function extractPhone($value, $pid_value)
    {
        $phone = new \stdClass();
        $phone->area_code = "";
        $phone->country_code = "";
        $phone->ext = "";
        $phone->number = "";
        $phone->type = "";

        if(isset($value->{'PID.'.$pid_value.'.3'}) && !is_object($value->{'PID.'.$pid_value.'.3'}))
        {
            $phone->type = $value->{'PID.'.$pid_value.'.3'};
        }
        else if(isset($value->{'PID.'.$pid_value.'.2'}) && !is_object($value->{'PID.'.$pid_value.'.2'}))
        {
            $phone->type = $value->{'PID.'.$pid_value.'.2'};
        }
        else
        {
            $phone->type = "";
        }

        if($pid_value == 14)
        {
            $phone->type = 'WRK';
        }

        if(isset($value->{'PID.'.$pid_value.'.1'}) && !is_object($value->{'PID.'.$pid_value.'.1'}) && $value->{'PID.'.$pid_value.'.1'} != "")
        {
            $phone->number = $value->{'PID.'.$pid_value.'.1'};
        }
        else if(isset($value->{'PID.'.$pid_value.'.6'}) && !is_object($value->{'PID.'.$pid_value.'.6'}) && isset($value->{'PID.'.$pid_value.'.7'}) && !is_object($value->{'PID.'.$pid_value.'.7'}))
        {
            $phone->number = $value->{'PID.'.$pid_value.'.6'}.$value->{'PID.'.$pid_value.'.7'};
        }
        else
        {
            $phone->number = "";
        }

        if($phone->number == "")
        {
            return null;
        }
        else if($phone->type == "NET")
        {
            return null;
        }

        $phone->type = $this->normalizePhoneTypes($phone->type);
        $phone->number = $this->formatPhone($phone->number);

        return $phone;
    }

    /**
     * @param $type
     * @return string
     */
    private function normalizePhoneTypes($type)
    {
        if($type == "")
        {
            $type = 'PRN';
        }
        switch (strtoupper($type))
        {
            case "HOME":
            case "PRN":
                return "Primary Residence Number";
            case "ORN":
                return "Other Residence Number";
            case "WPN":
            case "WRK":
                return "Work Number";
            case "VHN":
                return "Vacation Home Number";
            case "ASN":
                return "Answering Service Number";
            case "EMR":
                return "Emergency Number";
            case "NET":
            case "Internet":
            case "X.400":
                return "Network (email) Address";
            case "BPN":
            case "BP":
                return "Beeper Number";
            case "CP":
            case "CELL":
                return "Cell Phone";
            case "FX":
                return "Fax";
            case "MD":
                return "Modem";
            case "PH":
                return "Telephone";
            case "TDD":
                return "Telecommunications Device for the Deaf";
            case "TTY":
                return "Teletypewriter";
            default:
                return $type;
        }
    }

    /**
     * @param $input
     * @return bool|string|null
     */
    private function formatPhone($input)
    {
        if(is_object($input))
        {
            return "";
        }

        $phone = preg_replace( '/[^0-9]/', '', $input);
        if(strlen($phone) > 10)
        {
            return substr($phone, -10);
        }
        if(strlen($phone) == 8)
        {
            return preg_replace("/([0-9a-zA-Z]{3})([0-9a-zA-Z]{4})/", "$1-$2", $phone);
        }
        else if(strlen($phone) == 10)
        {
            return preg_replace("/([0-9a-zA-Z]{3})([0-9a-zA-Z]{3})([0-9a-zA-Z]{4})/", "+1($1)$2-$3", $phone);
        }
        return $input;
    }

    private function extractAddress($value)
    {
        $address = new \stdClass();
        $address->street_1 = null;
        $address->street_2 = null;
        $address->city = null;
        $address->state = null;
        $address->zip = null;

        if (isset($value->{'PID.11.1'})) {
            $address->street_1 = $value->{'PID.11.1'};
        }

        if (isset($value->{'PID.11.2'})) {
            $address->street_2 = $value->{'PID.11.2'};
        }

        if (isset($value->{'PID.11.3'})) {
            $address->city = $value->{'PID.11.3'};
        }

        if (isset($value->{'PID.11.4'})) {
            $address->state = $value->{'PID.11.4'};
        }

        if (isset($value->{'PID.11.5'})) {
            $address->zip = $this->formatZip($value->{'PID.11.5'});
        }

        if (isset($value->{'PID.11.6'})) {
            $address->country = $this->normalizeCountry($value->{'PID.11.6'});
        }

        if(is_null($address->street_1)  && is_null($address->street_2) && is_null($address->city) && is_null($address->state) && is_null($address->zip))
        {
            return null;
        }
        return $address;
    }

    /**
     * @param $input
     * @return string|null
     */
    private function formatZip($input)
    {
        $zip = preg_replace( '/[^0-9]/', '', $input);

        if(strlen($zip) == 5)
        {
            return $zip;
        }
        if(strlen($zip) == 9)
        {
            return substr($zip, 0,5)."-".substr($zip, 5);
        }
        return $input;
    }

    /**
     * @param $source
     * @return string
     */
    private function normalizeAdmitSource($source)
    {
        switch ($source)
        {
            case 1:
                return "Physician referral";
            case 2:
                return "Clinic referral";
            case 3:
                return "HMO referral";
            case 4:
                return "Transfer from a hospital";
            case 5:
                return "Transfer from a skilled nursing facility";
            case 6:
                return "Transfer from another health care facility";
            case 7:
                return "Emergency room";
            case 8:
                return "Court/law enforcement";
            case 9:
                return "Information not available";
            default:
                return $source;
        }
    }

    /**
     * @param $race
     * @return string
     */
    private function normalizeRace($race)
    {
        switch ($race)
        {
            case "1002-5":
                return "American Indian or Alaska Native";
            case "2028-9":
                return "Asian";
            case "2054-5":
                return "Black or African American";
            case "2076-8":
                return "Native Hawaiian or Other Pacific Islander";
            case "2106-3":
                return "White";
            case "2131-1":
                return "Other Race";
            default:
                return $race;
        }
    }

    /**
     * @param $type
     * @return string
     */
    private function normalizeMessageType($type)
    {
        switch (strtoupper($type))
        {
            case 'A01':
                return "Admit - A01";
            case 'A02':
                return "Transfer to Inp - A02";
            case 'A03':
                return "Discharge - A03";
            case 'A04':
                return "Register - A04";
            case 'A06':
                return "Outpatient to Inpatient - A06";
            case 'A09':
                return "Patient Departing - A09";
            case 'A08':
                return "Patient Information Update - A08";
            default:
                return $type;
        }
    }

    //<editor-fold desc="Countries">
    private function normalizeCountry($country)
    {
        $country = strtoupper($country);
        switch ($country) {
            case "ABW":
                return "Aruba";
            case "AFG":
                return "Afghanistan";
            case "AGO":
                return "Angola";
            case "AIA":
                return "Anguilla";
            case "ALA":
                return "Åland Islands";
            case "ALB":
                return "Albania";
            case "AND":
                return "Andorra";
            case "ARE":
                return "United Arab Emirates";
            case "ARG":
                return "Argentina";
            case "ARM":
                return "Armenia";
            case "ASM":
                return "American Samoa";
            case "ATA":
                return "Antarctica";
            case "ATF":
                return "French Southern Territories";
            case "ATG":
                return "Antigua and Barbuda";
            case "AUS":
                return "Australia";
            case "AUT":
                return "Austria";
            case "AZE":
                return "Azerbaijan";
            case "BDI":
                return "Burundi";
            case "BEL":
                return "Belgium";
            case "BEN":
                return "Benin";
            case "BES":
                return "Bonaire, Saint Eustatius and Saba";
            case "BFA":
                return "Burkina Faso";
            case "BGD":
                return "Bangladesh";
            case "BGR":
                return "Bulgaria";
            case "BHR":
                return "Bahrain";
            case "BHS":
                return "Bahamas";
            case "BIH":
                return "Bosnia and Herzegovina";
            case "BLM":
                return "Saint Barthélemy";
            case "BLR":
                return "Belarus";
            case "BLZ":
                return "Belize";
            case "BMU":
                return "Bermuda";
            case "BOL":
                return "Bolivia, Plurinational State of";
            case "BRA":
                return "Brazil";
            case "BRB":
                return "Barbados";
            case "BRN":
                return "Brunei Darussalam";
            case "BTN":
                return "Bhutan";
            case "BVT":
                return "Bouvet Island";
            case "BWA":
                return "Botswana";
            case "CAF":
                return "Central African Republic";
            case "CAN":
                return "Canada";
            case "CCK":
                return "Cocos (Keeling) Islands";
            case "CHE":
                return "Switzerland";
            case "CHL":
                return "Chile";
            case "CHN":
                return "China";
            case "CIV":
                return "Côte d'Ivoire";
            case "CMR":
                return "Cameroon";
            case "COD":
                return "Congo, the Democratic Republic of the";
            case "COG":
                return "Congo";
            case "COK":
                return "Cook Islands";
            case "COL":
                return "Colombia";
            case "COM":
                return "Comoros";
            case "CPV":
                return "Cape Verde";
            case "CRI":
                return "Costa Rica";
            case "CUB":
                return "Cuba";
            case "CUW":
                return "Curaçao";
            case "CXR":
                return "Christmas Island";
            case "CYM":
                return "Cayman Islands";
            case "CYP":
                return "Cyprus";
            case "CZE":
                return "Czech Republic";
            case "DEU":
                return "Germany";
            case "DJI":
                return "Djibouti";
            case "DMA":
                return "Dominica";
            case "DNK":
                return "Denmark";
            case "DOM":
                return "Dominican Republic";
            case "DZA":
                return "Algeria";
            case "ECU":
                return "Ecuador";
            case "EGY":
                return "Egypt";
            case "ERI":
                return "Eritrea";
            case "ESH":
                return "Western Sahara";
            case "ESP":
                return "Spain";
            case "EST":
                return "Estonia";
            case "ETH":
                return "Ethiopia";
            case "FIN":
                return "Finland";
            case "FJI":
                return "Fiji";
            case "FLK":
                return "Falkland Islands (Malvinas)";
            case "FRA":
                return "France";
            case "FRO":
                return "Faroe Islands";
            case "FSM":
                return "Micronesia, Federated States of";
            case "GAB":
                return "Gabon";
            case "GBR":
                return "United Kingdom";
            case "GEO":
                return "Georgia";
            case "GGY":
                return "Guernsey";
            case "GHA":
                return "Ghana";
            case "GIB":
                return "Gibraltar";
            case "GIN":
                return "Guinea";
            case "GLP":
                return "Guadeloupe";
            case "GMB":
                return "Gambia";
            case "GNB":
                return "Guinea-Bissau";
            case "GNQ":
                return "Equatorial Guinea";
            case "GRC":
                return "Greece";
            case "GRD":
                return "Grenada";
            case "GRL":
                return "Greenland";
            case "GTM":
                return "Guatemala";
            case "GUF":
                return "French Guiana";
            case "GUM":
                return "Guam";
            case "GUY":
                return "Guyana";
            case "HKG":
                return "Hong Kong";
            case "HMD":
                return "Heard Island and McDonald Islands";
            case "HND":
                return "Honduras";
            case "HRV":
                return "Croatia";
            case "HTI":
                return "Haiti";
            case "HUN":
                return "Hungary";
            case "IDN":
                return "Indonesia";
            case "IMN":
                return "Isle of Man";
            case "IND":
                return "India";
            case "IOT":
                return "British Indian Ocean Territory";
            case "IRL":
                return "Ireland";
            case "IRN":
                return "Iran, Islamic Republic of";
            case "IRQ":
                return "Iraq";
            case "ISL":
                return "Iceland";
            case "ISR":
                return "Israel";
            case "ITA":
                return "Italy";
            case "JAM":
                return "Jamaica";
            case "JEY":
                return "Jersey";
            case "JOR":
                return "Jordan";
            case "JPN":
                return "Japan";
            case "KAZ":
                return "Kazakhstan";
            case "KEN":
                return "Kenya";
            case "KGZ":
                return "Kyrgyzstan";
            case "KHM":
                return "Cambodia";
            case "KIR":
                return "Kiribati";
            case "KNA":
                return "Saint Kitts and Nevis";
            case "KOR":
                return "Korea, Republic of";
            case "KWT":
                return "Kuwait";
            case "LAO":
                return "Lao People's Democratic Republic";
            case "LBN":
                return "Lebanon";
            case "LBR":
                return "Liberia";
            case "LBY":
                return "Libyan Arab Jamahiriya";
            case "LCA":
                return "Saint Lucia";
            case "LIE":
                return "Liechtenstein";
            case "LKA":
                return "Sri Lanka";
            case "LSO":
                return "Lesotho";
            case "LTU":
                return "Lithuania";
            case "LUX":
                return "Luxembourg";
            case "LVA":
                return "Latvia";
            case "MAC":
                return "Macao";
            case "MAF":
                return "Saint Martin (French part)";
            case "MAR":
                return "Morocco";
            case "MCO":
                return "Monaco";
            case "MDA":
                return "Moldova, Republic of";
            case "MDG":
                return "Madagascar";
            case "MDV":
                return "Maldives";
            case "MEX":
                return "Mexico";
            case "MHL":
                return "Marshall Islands";
            case "MKD":
                return "Macedonia, the former Yugoslav Republic of";
            case "MLI":
                return "Mali";
            case "MLT":
                return "Malta";
            case "MMR":
                return "Myanmar";
            case "MNE":
                return "Montenegro";
            case "MNG":
                return "Mongolia";
            case "MNP":
                return "Northern Mariana Islands";
            case "MOZ":
                return "Mozambique";
            case "MRT":
                return "Mauritania";
            case "MSR":
                return "Montserrat";
            case "MTQ":
                return "Martinique";
            case "MUS":
                return "Mauritius";
            case "MWI":
                return "Malawi";
            case "MYS":
                return "Malaysia";
            case "MYT":
                return "Mayotte";
            case "NAM":
                return "Namibia";
            case "NCL":
                return "New Caledonia";
            case "NER":
                return "Niger";
            case "NFK":
                return "Norfolk Island";
            case "NGA":
                return "Nigeria";
            case "NIC":
                return "Nicaragua";
            case "NIU":
                return "Niue";
            case "NLD":
                return "Netherlands";
            case "NOR":
                return "Norway";
            case "NPL":
                return "Nepal";
            case "NRU":
                return "Nauru";
            case "NZL":
                return "New Zealand";
            case "OMN":
                return "Oman";
            case "PAK":
                return "Pakistan";
            case "PAN":
                return "Panama";
            case "PCN":
                return "Pitcairn";
            case "PER":
                return "Peru";
            case "PHL":
                return "Philippines";
            case "PLW":
                return "Palau";
            case "PNG":
                return "Papua New Guinea";
            case "POL":
                return "Poland";
            case "PRI":
                return "Puerto Rico";
            case "PRK":
                return "Korea, Democratic People's Republic of";
            case "PRT":
                return "Portugal";
            case "PRY":
                return "Paraguay";
            case "PSE":
                return "Palestinian Territory, Occupied";
            case "PYF":
                return "French Polynesia";
            case "QAT":
                return "Qatar";
            case "REU":
                return "Réunion";
            case "ROU":
                return "Romania";
            case "RUS":
                return "Russian Federation";
            case "RWA":
                return "Rwanda";
            case "SAU":
                return "Saudi Arabia";
            case "SDN":
                return "Sudan";
            case "SEN":
                return "Senegal";
            case "SGP":
                return "Singapore";
            case "SGS":
                return "South Georgia and the South Sandwich Islands";
            case "SHN":
                return "Saint Helena, Ascension and Tristan da Cunha";
            case "SJM":
                return "Svalbard and Jan Mayen";
            case "SLB":
                return "Solomon Islands";
            case "SLE":
                return "Sierra Leone";
            case "SLV":
                return "El Salvador";
            case "SMR":
                return "San Marino";
            case "SOM":
                return "Somalia";
            case "SPM":
                return "Saint Pierre and Miquelon";
            case "SRB":
                return "Serbia";
            case "STP":
                return "Sao Tome and Principe";
            case "SUR":
                return "Suriname";
            case "SVK":
                return "Slovakia";
            case "SVN":
                return "Slovenia";
            case "SWE":
                return "Sweden";
            case "SWZ":
                return "Swaziland";
            case "SXM":
                return "Sint Maarten (Dutch part)";
            case "SYC":
                return "Seychelles";
            case "SYR":
                return "Syrian Arab Republic";
            case "TCA":
                return "Turks and Caicos Islands";
            case "TCD":
                return "Chad";
            case "TGO":
                return "Togo";
            case "THA":
                return "Thailand";
            case "TJK":
                return "Tajikistan";
            case "TKL":
                return "Tokelau";
            case "TKM":
                return "Turkmenistan";
            case "TLS":
                return "Timor-Leste";
            case "TON":
                return "Tonga";
            case "TTO":
                return "Trinidad and Tobago";
            case "TUN":
                return "Tunisia";
            case "TUR":
                return "Turkey";
            case "TUV":
                return "Tuvalu";
            case "TWN":
                return "Taiwan, Province of China";
            case "TZA":
                return "Tanzania, United Republic of";
            case "UGA":
                return "Uganda";
            case "UKR":
                return "Ukraine";
            case "UMI":
                return "United States Minor Outlying Islands";
            case "URY":
                return "Uruguay";
            case "USA":
                return "United States";
            case "UZB":
                return "Uzbekistan";
            case "VAT":
                return "Holy See (Vatican City State)";
            case "VCT":
                return "Saint Vincent and the Grenadines";
            case "VEN":
                return "Venezuela, Bolivarian Republic of";
            case "VGB":
                return "Virgin Islands, British";
            case "VIR":
                return "Virgin Islands, U.S.";
            case "VNM":
                return "Viet Nam";
            case "VUT":
                return "Vanuatu";
            case "WLF":
                return "Wallis and Futuna";
            case "WSM":
                return "Samoa";
            case "YEM":
                return "Yemen";
            case "ZAF":
                return "South Africa";
            case "ZMB":
                return "Zambia";
            case "ZWE":
                return "Zimbabwe";
            default:
                return $country;
        }
    }
    //</editor-fold>

}
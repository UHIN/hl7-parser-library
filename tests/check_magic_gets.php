<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\RequestOptions;

include('../src/hl7/HL7.php');
include('../src/hl7/Segment.php');
include('../src/hl7/Field.php');
include('../src/hl7/SubField.php');
include('../src/hl7/MSH.php');
include('../src/hl7/Separators.php');
include('../vendor/autoload.php');



$files = scandir('../sample_files/');

foreach ($files as $file)
{
    try
    {
        if ($file == '.' || $file == '..' || is_dir('../sample_files/' . $file))
        {

        }
        else
        {
            echo 'Processing '.$file."\r\n";
            $data = file_get_contents('../sample_files/' . $file);
            $hl7 = new \Uhin\HL7\HL7();
            $hl7->parse($data);

//            echo "First Name: ".$hl7->first_name."\r\n";
//            echo "Middle Name: ".$hl7->middle_name."\r\n";
//            echo "Last Name: ".$hl7->last_name."\r\n";
//            echo "Facility Name: ".$hl7->facility_name."\r\n";
//            echo "Message Type: ".$hl7->message_type."\r\n";
//            echo "Patient Type: ".$hl7->patient_type."\r\n";
//            echo "Control Number: ".$hl7->control_number."\r\n";
//            echo "Event Time: ".$hl7->event_time."\r\n";
//            echo "EID : ".$hl7->eid."\r\n";

            if(is_null($hl7->first_name))
            {
                echo $file." first name was null.\r\n";
            }

            if(is_null($hl7->middle_name))
            {
                echo $file." middle name was null.\r\n";
            }

            if(is_null($hl7->last_name))
            {
                echo $file." last name was null.\r\n";
            }

            if(is_null($hl7->message_type))
            {
                echo $file." message type was null.\r\n";
            }

            if(is_null($hl7->patient_type))
            {
                echo $file." patient type was null.\r\n";
            }

            if(is_null($hl7->control_number))
            {
                echo $file." control number was null.\r\n";
            }

            if(is_null($hl7->event_time))
            {
                echo $file." event time was null.\r\n";
            }

            if(is_null($hl7->facility_name))
            {
                echo $file." facility name was null.\r\n";
            }

            if(is_null($hl7->eid))
            {
                echo $file." eid was null.\r\n";
            }


        }
    }
    catch (Exception $e)
    {
        echo $file." ".$e->getMessage()."\r\n";
    }
}
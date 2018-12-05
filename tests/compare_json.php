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

            $libJSONFile = '../sample_files/compare/' . $file . '.json';
            $mirthJSONFile = '../sample_files/compare/' . $file . '.mirth.json';

            file_put_contents($libJSONFile, json_encode($hl7, JSON_PRETTY_PRINT));

            $mirth = getHL7XML(base64_encode($data));

            if(isset($mirth->NK1))
            {
                if(!is_array($mirth->NK1))
                {
                    $temp = $mirth->NK1;
                    $mirth->NK1 = [];
                    $mirth->NK1[] = $temp;
                }
            }

            if(isset($mirth->DG1))
            {
                if(!is_array($mirth->DG1))
                {
                    $temp = $mirth->DG1;
                    $mirth->DG1 = [];
                    $mirth->DG1[] = $temp;
                }
            }

            if(isset($mirth->OBX))
            {
                if(!is_array($mirth->OBX))
                {
                    $temp = $mirth->OBX;
                    $mirth->OBX = [];
                    $mirth->OBX[] = $temp;
                }
            }

            if(isset($mirth->PR1))
            {
                if(!is_array($mirth->PR1))
                {
                    $temp = $mirth->PR1;
                    $mirth->PR1 = [];
                    $mirth->PR1[] = $temp;
                }
            }

            if(isset($mirth->NTE))
            {
                if(!is_array($mirth->NTE))
                {
                    $temp = $mirth->NTE;
                    $mirth->NTE = [];
                    $mirth->NTE[] = $temp;
                }
            }

            if(isset($mirth->AL1))
            {
                if(!is_array($mirth->AL1))
                {
                    $temp = $mirth->AL1;
                    $mirth->AL1 = [];
                    $mirth->AL1[] = $temp;
                }
            }

            if(isset($mirth->ACC))
            {
                if(!is_array($mirth->ACC))
                {
                    $temp = $mirth->ACC;
                    $mirth->ACC = [];
                    $mirth->ACC[] = $temp;
                }
            }

            if(isset($mirth->IAM))
            {
                if(!is_array($mirth->IAM))
                {
                    $temp = $mirth->IAM;
                    $mirth->IAM = [];
                    $mirth->IAM[] = $temp;
                }
            }

            $mirth = json_encode($mirth,JSON_PRETTY_PRINT); 
            $mirth = str_replace('{}','""',$mirth);

            file_put_contents($mirthJSONFile, $mirth);

            $output = [];
            exec('json_diff -u '.$libJSONFile.' '.$mirthJSONFile,$output);

            /* Filter out normal output and write anything remaaining to a compare file. */
            $compare = "";
            for ($i = 2;$i < count($output); $i++)
            {
                if($output[$i] != " {...}")
                {
                    $compare = $compare."\r\n".$output[$i];
                }

            }

            if(strlen($compare) > 0)
            {
                file_put_contents('../sample_files/compare/' . $file . '.compare',$compare);
            }
            else
            {
                unlink($libJSONFile);
                unlink($mirthJSONFile);
            }
        }
    }
    catch (Exception $e)
    {
        echo $file." ".$e->getMessage()."\r\n";
    }
}

/**
 * @param $adt
 * @return mixed
 * @throws Exception
 */
function getHL7XML($adt)
{
    $url = 'http://hl7.prod.uhin.org/hl7s';

    $body = array('data'=>array('hl7' => $adt));

    $client = new Client();

    try
    {
        $response = $client->post($url ,[RequestOptions::JSON => $body]);
        $code = $response->getStatusCode();
        $body = $response->getBody();
        $reason = $response->getReasonPhrase();
    }
    catch (ClientException $e)
    {
        $response = $e->getResponse();
        $body = $response->getBody()->getContents();
        $json = json_decode($body);
        if(isset($json->errors))
        {
            throw new \Exception("HL7 Service Failure: ".$json->errors[0]->detail,$json->errors[0]->status);
        }
        else
        {
            throw $e;
        }
    }
    /* Check the status code */
    if($code != 201)
    {
        throw new \Exception("Desired response code was not returned. Reason: ".$reason, $code);
    }

    $body = json_decode($body);

    if($body == '' || $body === false)
    {
        throw new \Exception("Failed to parse HL7 message.",500);
    }

    if(!isset($body->data->hl7))
    {
        throw new \Exception("Failed to parse HL7 message.",500);
    }

    return $body->data->hl7;
}
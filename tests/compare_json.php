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

            $mirth = json_encode(getHL7XML(base64_encode($data)),JSON_PRETTY_PRINT);
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
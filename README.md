# hl7-parser-library

#Usage

```php
$hl7 = new \Uhin\HL7\HL7();
$hl7->parse($adt);
$facility = $hl7->facility_name;
$facility = $hl7->MSH->{'MSH.4'}->{'MSH.4.1'};  
```

#### Version 1.0.8 9/11/2019
#### Version 1.0.9 10/22/2019

# hl7-parser-library

#Usage

```php
$hl7 = new \uhin\hL7\HL7();
$hl7->parse($adt);
$facility = $hl7->facility_name;
$facility = $hl7->MSH->{'MSH.4'}->{'MSH.4.1'};  
```

#### Version 1.0.9 10/22/2019
#### Version 1.0.10 10/22/2019
#### Version 1.0.18 2/4/2020
#### Version 2.1.0 6/8/2021

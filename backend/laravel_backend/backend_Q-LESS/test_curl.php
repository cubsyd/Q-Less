<?php
$ch = curl_init('https://api.openai.com/v1/models');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_VERBOSE, true);
$result = curl_exec($ch);
echo curl_error($ch);
echo substr($result, 0, 200);
curl_close($ch);

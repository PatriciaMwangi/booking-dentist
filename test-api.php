<?php
// Simple test to see if we can reach the API
$baseUrl = "http://localhost/booking-dentist"; // Adjust for your setup
$testDate = date('Y-m-d');

$url = $baseUrl . "/get_duty_status.php?date=" . $testDate;
echo "Testing URL: " . $url . "<br><br>";

$response = file_get_contents($url);
echo "Response:<br>";
echo htmlspecialchars(substr($response, 0, 500));
?>
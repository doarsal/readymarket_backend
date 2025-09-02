<?php
$url = 'http://localhost:8000/api/v1/products?category_id=4&per_page=1';
$response = file_get_contents($url);
$data = json_decode($response, true);

echo "API Response:\n";
echo json_encode($data, JSON_PRETTY_PRINT);

if (isset($data['data'][0]['category'])) {
    echo "\n\nCategory info found:\n";
    echo json_encode($data['data'][0]['category'], JSON_PRETTY_PRINT);
}
?>

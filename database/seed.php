<?php
require_once __DIR__ . '/../config/db.php';

// ── Admin ─────────────────────────────────────────────────────────────────
$pdo->prepare("INSERT IGNORE INTO admin (username, password) VALUES (?, ?)")
    ->execute(['admin', password_hash('Admin@VRS2026', PASSWORD_BCRYPT)]);
echo "Admin seeded.\n";

// ── Vehicles ──────────────────────────────────────────────────────────────
$vehicles = [
    [
        'name'             => 'Suzuki Swift',
        'type'             => 'Car',
        'fuel_type'        => 'Petrol',
        'seats'            => 5,
        'transmission'     => 'Manual',
        'year'             => 2022,
        'doors'            => 4,
        'mileage'          => '18 km/l',
        'luggage_capacity' => '2 Bags',
        'pickup_location'  => 'Thamel, Kathmandu',
        'description'      => 'The Suzuki Swift is a stylish and fuel-efficient hatchback perfect for city driving and long trips. It offers a smooth drive, modern features, and excellent comfort.',
        'features'         => json_encode(['Air Conditioning','Bluetooth','Power Steering','USB Charging','ABS Brakes']),
        'price_per_day'    => 3500,
        'images'           => [
            'https://images.unsplash.com/photo-1609521263047-f8f205293f24?w=800&q=80',
            'https://images.unsplash.com/photo-1541899481282-d53bffe3c35d?w=800&q=80',
            'https://images.unsplash.com/photo-1503376780353-7e6692767b70?w=800&q=80',
        ],
    ],
    [
        'name'             => 'Royal Enfield Himalayan',
        'type'             => 'Motorcycle',
        'fuel_type'        => 'Petrol',
        'seats'            => 2,
        'transmission'     => 'Manual',
        'year'             => 2023,
        'doors'            => null,
        'mileage'          => '30 km/l',
        'luggage_capacity' => '1 Bag',
        'pickup_location'  => 'Thamel, Kathmandu',
        'description'      => 'Built for adventure, the Royal Enfield Himalayan is perfect for mountain trails and long highway rides across Nepal.',
        'features'         => json_encode(['Knobby Tyres','Crash Guard','USB Charging','Windshield','Luggage Rack']),
        'price_per_day'    => 2500,
        'images'           => [
            'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=800&q=80',
            'https://images.unsplash.com/photo-1449426468159-d96dbf08f19f?w=800&q=80',
        ],
    ],
    [
        'name'             => 'Hyundai Creta',
        'type'             => 'SUV',
        'fuel_type'        => 'Diesel',
        'seats'            => 5,
        'transmission'     => 'Automatic',
        'year'             => 2023,
        'doors'            => 4,
        'mileage'          => '17 km/l',
        'luggage_capacity' => '3 Bags',
        'pickup_location'  => 'Lazimpat, Kathmandu',
        'description'      => 'The Hyundai Creta is a premium SUV offering a commanding road presence, spacious cabin, and advanced safety features for family trips.',
        'features'         => json_encode(['Sunroof','360° Camera','Lane Assist','Heated Seats','Apple CarPlay']),
        'price_per_day'    => 6500,
        'images'           => [
            'https://images.unsplash.com/photo-1519641471654-76ce0107ad1b?w=800&q=80',
            'https://images.unsplash.com/photo-1533473359331-0135ef1b58bf?w=800&q=80',
        ],
    ],
    [
        'name'             => 'Toyota Corolla',
        'type'             => 'Car',
        'fuel_type'        => 'Petrol',
        'seats'            => 5,
        'transmission'     => 'Automatic',
        'year'             => 2021,
        'doors'            => 4,
        'mileage'          => '15 km/l',
        'luggage_capacity' => '2 Bags',
        'pickup_location'  => 'New Baneshwor, Kathmandu',
        'description'      => 'A reliable and comfortable sedan ideal for business travel and family outings across Nepal.',
        'features'         => json_encode(['Air Conditioning','Reverse Camera','Cruise Control','Bluetooth','Power Windows']),
        'price_per_day'    => 4500,
        'images'           => [
            'https://images.unsplash.com/photo-1623869675781-80aa31012a5a?w=800&q=80',
            'https://images.unsplash.com/photo-1550355291-bbee04a92027?w=800&q=80',
        ],
    ],
    [
        'name'             => 'Toyota HiAce',
        'type'             => 'Van',
        'fuel_type'        => 'Diesel',
        'seats'            => 12,
        'transmission'     => 'Manual',
        'year'             => 2020,
        'doors'            => 4,
        'mileage'          => '12 km/l',
        'luggage_capacity' => '6 Bags',
        'pickup_location'  => 'Kalanki, Kathmandu',
        'description'      => 'Perfect for group travel, the Toyota HiAce offers ample seating and luggage space for tours and corporate transfers.',
        'features'         => json_encode(['Air Conditioning','Reclining Seats','Large Windows','USB Charging','First Aid Kit']),
        'price_per_day'    => 8000,
        'images'           => [
            'https://images.unsplash.com/photo-1544636331-e26879cd4d9b?w=800&q=80',
        ],
    ],
];

foreach ($vehicles as $v) {
    $images = $v['images'];
    unset($v['images']);

    // Check if already exists
    $check = $pdo->prepare("SELECT id FROM vehicles WHERE name = ?");
    $check->execute([$v['name']]);
    if ($check->fetchColumn()) { echo "Skipped (exists): {$v['name']}\n"; continue; }

    $pdo->prepare("
        INSERT INTO vehicles (name,type,fuel_type,seats,transmission,year,doors,mileage,luggage_capacity,pickup_location,description,features,price_per_day,available)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,1)
    ")->execute([
        $v['name'],$v['type'],$v['fuel_type'],$v['seats'],$v['transmission'],
        $v['year'],$v['doors'],$v['mileage'],$v['luggage_capacity'],
        $v['pickup_location'],$v['description'],$v['features'],$v['price_per_day'],
    ]);
    $vid = (int)$pdo->lastInsertId();

    foreach ($images as $i => $url) {
        $pdo->prepare("INSERT INTO vehicle_images (vehicle_id, image_path, is_primary, sort_order) VALUES (?,?,?,?)")
            ->execute([$vid, $url, $i === 0 ? 1 : 0, $i]);
    }
    echo "Seeded: {$v['name']}\n";
}

echo "Done.\n";

// ── Terms ─────────────────────────────────────────────────────────────────
$existing = (int)$pdo->query("SELECT COUNT(*) FROM terms")->fetchColumn();
if ($existing === 0) {
    $defaultTerms = [
        ['Eligibility', 'Renters must be 18 years or older with a valid driving license issued by the Government of Nepal or an International Driving Permit.', 0],
        ['Rental Period', 'The rental period begins and ends as specified in the booking confirmation. Extensions must be requested 12 hours in advance.', 1],
        ['Vehicle Condition', 'The vehicle must be returned in the same condition as received. Any damage will be assessed and charged accordingly.', 2],
        ['Fuel Policy', 'Vehicles are provided with a full tank. Renters must return the vehicle with a full tank or pay applicable fuel charges.', 3],
        ['Insurance', 'Basic insurance coverage is included. Comprehensive coverage is available at additional cost.', 4],
        ['Cancellation Policy', 'Free cancellation up to 24 hours before pickup. 50% charge for cancellation within 24 hours. No refund for no-shows.', 5],
        ['Prohibited Uses', 'Off-road driving (unless designated), racing, subletting, or use for illegal activities are strictly prohibited.', 6],
        ['Security Deposit', 'A refundable security deposit is collected at handover and returned within 5 business days after return.', 7],
    ];
    $stmt = $pdo->prepare("INSERT INTO terms (title, content, sort_order) VALUES (?, ?, ?)");
    foreach ($defaultTerms as $t) $stmt->execute($t);
    echo "Terms seeded.\n";
} else {
    echo "Terms already exist, skipping.\n";
}

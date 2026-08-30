<?php
/**
 * Skrypt do masowego przeredagowania wyjaśnień w bazie pytań za pomocą API Gemini.
 * Działa tylko na pytaniach bez obrazka.
 * 
 * Sposób uruchomienia: php scripts/rewrite_explanations.php
 */

require __DIR__ . '/../vendor/autoload.php';

// Ładowanie zmiennych środowiskowych
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$apiKey = $_ENV['GEMINI_API_KEY'] ?? 'AQ.Ab8RN6IeGxRBWvBYTWui29S5whZmeBgRGJVXCfssacaT-Ns_sA';
$dbHost = $_ENV['DB_HOST'] ?? '127.0.0.1';
$dbName = $_ENV['DB_NAME'] ?? 'zdajnik';
$dbUser = $_ENV['DB_USER'] ?? 'root';
$dbPass = $_ENV['DB_PASS'] ?? '';
$examCode = 'INF.04'; // Kwalifikacja do przetworzenia
$batchSize = 15; // Ilość pytań wysyłana w jednym zapytaniu do API

try {
    $pdo = new PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Błąd połączenia z bazą: " . $e->getMessage() . "\n");
}

echo "Pobieranie pytań dla kwalifikacji {$examCode}...\n";

// Pobieranie pytań bez obrazka z dołączonymi prawidłowymi odpowiedziami (by dać kontekst SI)
$query = "
    SELECT 
        qv.id as version_id,
        qv.question_text,
        qv.explanation,
        a.answer_text as correct_answer
    FROM questions q
    JOIN exam_types et ON q.exam_type_id = et.id
    JOIN question_versions qv ON q.id = qv.question_id
    JOIN answers a ON qv.id = a.question_version_id
    WHERE et.code = :code 
      AND (qv.image_name IS NULL OR qv.image_name = '')
      AND a.is_correct = 1
      AND qv.is_active = 1
";

$stmt = $pdo->prepare($query);
$stmt->execute(['code' => $examCode]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalQuestions = count($questions);
echo "Znaleziono {$totalQuestions} pytań bez obrazka do przeredagowania.\n\n";

if ($totalQuestions === 0) {
    die("Brak pytań do przetworzenia.\n");
}

$processedCount = 0;
$updatedCount = 0;

$batches = array_chunk($questions, $batchSize);

foreach ($batches as $batchIndex => $batch) {
    echo "Przetwarzanie paczki " . ($batchIndex + 1) . " z " . count($batches) . "...\n";
    
    // Przygotowanie danych wejściowych
    $inputData = [];
    foreach ($batch as $q) {
        $inputData[] = [
            'id' => $q['version_id'],
            'q' => trim($q['question_text']),
            'ans' => trim($q['correct_answer']),
            'exp' => trim($q['explanation'])
        ];
    }
    
    $prompt = "Jesteś doświadczonym programistą i mentorem dla uczniów technikum przygotowujących się do egzaminu INF.04. 
Przeredaguj poniższe wyjaśnienia do pytań.
Wytyczne STYLU:
1. Budź zaufanie, bądź jak doświadczony starszy kolega po fachu (ale bez przesady, unikaj kiczowatych żartów czy potocznych słówek typu 'ziomek'). Pełen profesjonalizm, ale podany lekkim tonem.
2. Zrozumiale 'na chłopski rozum' - jasno i wprost. Nie używaj suchego, encyklopedycznego żargonu.
3. BARDZO ZWIĘŹLE. Uczniowie nienawidzą 'ścian tekstu'. Wyjaśnienie ma mieć maksymalnie 2-3 krótkie zdania. Od razu do sedna.
4. Uczeń po przeczytaniu ma pomyśleć: 'Aha! No jasne'.
Oto tablica JSON z danymi. Zwróć WYŁĄCZNIE tablicę JSON, w której dla każdego 'id' przypiszesz nowo sformułowane wyjaśnienie pod kluczem 'new_exp'. Żadnego formatowania markdown, tylko czysty kod JSON.

Dane wejściowe:
" . json_encode($inputData, JSON_UNESCAPED_UNICODE);

    // Call Gemini API
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.7-flash:generateContent?key=' . $apiKey;
    
    $postData = [
        "contents" => [
            [
                "role" => "user",
                "parts" => [
                    ["text" => $prompt]
                ]
            ]
        ],
        "generationConfig" => [
            "responseMimeType" => "application/json"
        ]
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        echo "Błąd API: " . $response . "\n";
        continue;
    }
    
    $responseData = json_decode($response, true);
    $responseText = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? null;
    
    if (!$responseText) {
        echo "Nie udało się odczytać odpowiedzi od API.\n";
        continue;
    }
    
    // Oczyszczanie odpowiedzi (na wypadek gdyby model zwrócił ```json ... ``` mimo zakazu)
    $responseText = preg_replace('/^```json\s*/', '', $responseText);
    $responseText = preg_replace('/\s*```$/', '', $responseText);
    
    $parsedResults = json_decode($responseText, true);
    
    if (!is_array($parsedResults)) {
        echo "Błąd parsowania JSON od API.\n";
        continue;
    }
    
    // Aktualizacja w bazie
    $pdo->beginTransaction();
    try {
        $updateStmt = $pdo->prepare("UPDATE question_versions SET explanation = :exp WHERE id = :id");
        foreach ($parsedResults as $result) {
            if (isset($result['id']) && isset($result['new_exp'])) {
                $updateStmt->execute([
                    'exp' => trim($result['new_exp']),
                    'id' => $result['id']
                ]);
                $updatedCount++;
            }
        }
        $pdo->commit();
        echo "Zaktualizowano " . count($parsedResults) . " wyjaśnień.\n";
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "Błąd zapisu do bazy: " . $e->getMessage() . "\n";
    }
    
    $processedCount += count($batch);
    echo "Postęp: {$processedCount} / {$totalQuestions}\n\n";
    
    // Krótka pauza, by nie przekroczyć limitów API
    sleep(1);
}

echo "Zakończono! Łącznie zaktualizowano: {$updatedCount} wyjaśnień.\n";

<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Analisi Contraffazione</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .result-card.success { background: #d4edda; border-left: 6px solid #28a745; }
    .result-card.warning { background: #fff3cd; border-left: 6px solid #ffc107; }
    .result-card.danger { background: #f8d7da; border-left: 6px solid #dc3545; }
    .emoji { font-size: 2rem; line-height: 1; }
  </style>
</head>
<body class="bg-light">

<?php
$risultato = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    function toBase64($file) {
        return base64_encode(file_get_contents($file));
    }

    $data = [
        'tipologia' => $_POST['tipologia'],
        'marca' => $_POST['marca'],
        'immagini' => [
            toBase64($_FILES['foto1']['tmp_name']),
            toBase64($_FILES['foto2']['tmp_name']),
            toBase64($_FILES['foto3']['tmp_name']),
        ]
    ];

    $ch = curl_init('https://api-contraffazione.onrender.com/analizza-oggetto');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        $risultato = "<div class='alert alert-danger mt-4'>Errore nella richiesta: " . curl_error($ch) . "</div>";
    } else {
        $output = json_decode($response, true);
        $percentuale = $output['percentuale'] ?? null;
        $motivazioni = $output['motivazioni'] ?? [];

        if ($percentuale === null) {
            $risultato = "<div class='alert alert-warning mt-4'>Risposta malformata:<pre>$response</pre></div>";
        } else {
            $percentuale = intval($percentuale);
            if ($percentuale < 30) {
                $emoji = "✅";
                $style = "success";
                $messaggio = "Probabilità molto bassa di contraffazione";
            } elseif ($percentuale < 70) {
                $emoji = "⚠️";
                $style = "warning";
                $messaggio = "Possibile contraffazione: da approfondire";
            } else {
                $emoji = "❌";
                $style = "danger";
                $messaggio = "Alta probabilità di contraffazione!";
            }

            $risultato = "
            <div class='card result-card $style mt-4 shadow'>
              <div class='card-body'>
                <div class='d-flex align-items-center mb-3'>
                  <span class='emoji me-3'>$emoji</span>
                  <div>
                    <h5 class='card-title mb-1 fw-bold'>Probabilità di Contraffazione</h5>
                    <h1 class='display-6 fw-bold mb-0'>$percentuale%</h1>
                  </div>
                </div>
                <p class='mb-3'>$messaggio</p>
                <h6 class='fw-semibold'>Motivazioni:</h6>
                <ul>";
            foreach ($motivazioni as $m) {
                $risultato .= "<li>" . htmlentities($m) . "</li>";
            }
            $risultato .= "</ul>
              </div>
            </div>";
        }
    }
    curl_close($ch);
}
?>

<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-lg-6 col-md-8">
      <div class="card shadow rounded-4">
        <div class="card-body p-4">
          <h3 class="mb-4 text-center">Analisi Contraffazione</h3>
          <form method="POST" enctype="multipart/form-data">
            <div class="mb-3">
              <label class="form-label">Tipologia</label>
              <input type="text" class="form-control" name="tipologia" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Marca</label>
              <input type="text" class="form-control" name="marca" required>
            </div>
            <div class="mb-3"><input type="file" class="form-control" name="foto1" accept="image/*" required></div>
            <div class="mb-3"><input type="file" class="form-control" name="foto2" accept="image/*" required></div>
            <div class="mb-3"><input type="file" class="form-control" name="foto3" accept="image/*" required></div>
            <div class="d-grid">
              <button type="submit" class="btn btn-primary btn-lg">Analizza</button>
            </div>
          </form>
          <?= $risultato ?>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

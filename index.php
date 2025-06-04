<?php
session_start();
$step = $_SESSION['step'] ?? 1;
$immagini_base64 = $_SESSION['immagini'] ?? [];
$percentuali = $_SESSION['percentuali'] ?? [];
$risposta_api = $_SESSION['risposta_api'] ?? [];
$errore_api = null;


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['foto'])) {
    function toBase64($file) {
        return base64_encode(file_get_contents($file));
    }
    $immagini_base64[] = toBase64($_FILES['foto']['tmp_name']);
    $_SESSION['immagini'] = $immagini_base64;

    $data = [
        'tipologia' => $_POST['tipologia'] ?? $_SESSION['tipologia'] ?? 'borsa',
        'marca' => $_POST['marca'] ?? $_SESSION['marca'] ?? 'gucci',
        'immagini' => $immagini_base64
    ];
    $_SESSION['tipologia'] = $data['tipologia'];
    $_SESSION['marca'] = $data['marca'];

    $ch = curl_init('https://api-contraffazione-iter.onrender.com/analizza-oggetto');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    $response = curl_exec($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    $response = trim($response);
    //file_put_contents("debug_api_response.txt", "RISPOSTA:\n$response\nERRORE CURL:\n$curl_error\n", FILE_APPEND);
    

    $response = trim($response);
    $json = json_decode($response, true);

    if (!is_array($json)) {
        $errore_api = "⚠️ Errore di parsing JSON.<br><pre>" . htmlentities($response) . "</pre>";
    } elseif (!isset($json['percentuale'])) {
        $errore_api = "⚠️ Campo mancante: <code>percentuale</code><br><pre>" . htmlentities($response) . "</pre>";
    } elseif (!isset($json['richiedi_altra_foto'])) {
        $errore_api = "⚠️ Campo mancante: <code>richiedi_altra_foto</code><br><pre>" . htmlentities($response) . "</pre>";
    } else {
        $_SESSION['risposta_api'] = $json;
        $_SESSION['percentuali'][] = $json['percentuale'];

        if ($json['richiedi_altra_foto'] && count($_SESSION['immagini']) < 3) {
            $_SESSION['step'] = $step + 1;        
            $_SESSION['ultimo_messaggio'] = $json['dettaglio_richiesto'] ?? '';
        } else {
            $_SESSION['step'] = 99;
        }

        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

if (isset($_REQUEST['reset'])) {
    session_destroy();
    setcookie(session_name(), '', 0, '/');
    exit;
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Analisi Contraffazione</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-lg-6 col-md-8">
      <div class="card shadow rounded-4">
        <div class="card-body p-4">
          <h3 class="mb-4 text-center">Analisi Contraffazione</h3>

          <?php if (in_array($step, [1, 2, 3, 99])): ?>
            <form method="POST" enctype="multipart/form-data">
              <?php if ($step === 1): ?>
                <div class="mb-3">
                  <label class="form-label">Tipologia</label>
                  <input type="text" class="form-control" name="tipologia" required>
                </div>
                <div class="mb-3">
                  <label class="form-label">Marca</label>
                  <input type="text" class="form-control" name="marca" required>
                </div>
              <?php endif; ?>

              <?php if ($step !== 99): ?>
              <div class="mb-3">
                <label class="form-label">Carica foto <?php echo $step; ?></label>
                <input type="file" class="form-control" name="foto" accept="image/*" required onchange="previewImage(event)">
              </div>
              <div id="preview" class="text-center mb-3"></div>
              <?php endif; ?>

              <?php if (!empty($_SESSION['immagini'])): ?>
                <div class="mb-3">
                  <label class="form-label">Immagini caricate:</label>
                  <div class="d-flex gap-2 flex-wrap">
                    <?php foreach ($_SESSION['immagini'] as $img): ?>
                      <img src="data:image/jpeg;base64,<?= $img ?>" style="max-height: 80px; border-radius: 8px;">
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endif; ?>

              <?php if (!empty($_SESSION['percentuali'])): ?>
                <?php
                  $valide = array_filter($_SESSION['percentuali'], fn($p) => is_numeric($p));
                  $percentuale = count($valide) > 0 ? intval(round(array_sum($valide) / count($valide))) : 'N.D.';
                ?>
                <div class="alert alert-secondary text-center">
                  Percentuale attuale stimata: <strong><?= $percentuale ?><?= is_numeric($percentuale) ? '%' : '' ?></strong>
                </div>
              <?php endif; ?>

              <?php if (isset($_SESSION['ultimo_messaggio'])): ?>
                <div class="alert alert-info mt-3">
                  <strong>Dettaglio richiesto:</strong> <?= htmlentities($_SESSION['ultimo_messaggio']) ?>
                </div>
              <?php endif; ?>

              <?php if ($step !== 99): ?>
              <div class="d-grid">
                <button type="submit" class="btn btn-primary btn-lg">Invia</button>
              </div>
              <?php endif; ?>
            </form>

            <?php if ($step === 99): ?>
                <?php
                    $risposta_api = $_SESSION['risposta_api'] ?? [];
                    $percentuale = $risposta_api['percentuale'] ?? 'N.D.';
                    $motivazione = htmlentities($risposta_api['motivazione'] ?? 'Motivazione non disponibile.');

                    if ($percentuale === -1 || $percentuale === '-1') {
                        $icona = '❓';
                        $badge = 'bg-secondary';
                        $percentuale_label = "Non determinabile";
                    } else {
                        $valide = array_filter($_SESSION['percentuali'], fn($p) => is_numeric($p));
                        $percentuale = intval(round(array_sum($valide) / count($valide)));
                        $icona = $percentuale < 30 ? '🔒' : ($percentuale < 70 ? '⚠️' : '❌');
                        $badge = $percentuale < 30 ? 'bg-success' : ($percentuale < 70 ? 'bg-warning text-dark' : 'bg-danger');
                        $percentuale_label = $percentuale . '%';
                    }
                ?>
                <div class='card mt-4 shadow'>
                    <div class='card-body text-center'>
                    <h5 class='card-title'>Risultato Finale</h5>
                    <h1 class='display-4 mb-2'><?= "$icona <span class='badge $badge'>$percentuale_label</span>" ?></h1>
                    <p class='fw-bold text-muted'>Probabilità complessiva stimata di contraffazione</p>
                    <p class='text-start'><strong>Motivo:</strong> <?= $motivazione ?></p>
                    <?php if ($risposta_api['richiedi_altra_foto']): ?>
                        <p class='text-muted small mt-2'>⚠️ Ulteriori dettagli erano consigliati, ma è stata fornita una valutazione con le immagini disponibili.</p>
                    <?php endif; ?>

                    </div>
                </div>
                <pre class="bg-dark text-white mt-3 p-3 rounded small"><code><?= htmlentities(json_encode($risposta_api, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></code></pre>
                <?php endif; ?>


            <form method="POST" class="mt-3">
              <button type="submit" name="reset" class="btn btn-secondary w-100">Nuovo Oggetto</button>
            </form>
          <?php endif; ?>

          <?php if ($errore_api): ?>
            <div class='alert alert-danger mt-4'><?= $errore_api ?></div>
            <form method="POST">
              <button type="submit" name="reset" class="btn btn-secondary mt-3 w-100">Nuovo Oggetto</button>
            </form>
          <?php endif; ?>

        </div>
      </div>
    </div>
  </div>
</div>
<script>
function previewImage(event) {
  const preview = document.getElementById('preview');
  preview.innerHTML = '';
  const file = event.target.files[0];
  if (file) {
    const reader = new FileReader();
    reader.onload = function(e) {
      const img = document.createElement('img');
      img.src = e.target.result;
      img.style.maxWidth = '100%';
      img.style.borderRadius = '10px';
      preview.appendChild(img);
    }
    reader.readAsDataURL(file);
  }
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

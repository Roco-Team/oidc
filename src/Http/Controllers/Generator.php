<?php
namespace App\Http\Controllers;
use App\Support\Response;


class Generator {
    public function handle(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            ui();
            exit();
        }
        
        $f = isset($_POST['f']) ? htmlspecialchars($_POST['f']) : '';
        
        if ($f === 'totpgen') {
            $sec = \ParagonIE\ConstantTime\Base32::encodeUpper(random_bytes(10));
            Response::text($sec, 200);
        } else if ($f === 'pwdhash') {
            $oripwd = htmlspecialchars($_POST['text']);
            $hash = password_hash($oripwd, PASSWORD_ARGON2ID);
            Response::text($hash, 200);
        } else {
            Response::text('Unsupported',400);
            exit();
        }
    }
}


function ui() {
    require_once __DIR__ . '/../../../includes/adminheader.php';
    ?>
<div class="page-body">
  <div class="container-xl">
      <br>
<form method="POST">
  <div class="card">
    <div class="card-body">
      <div class="mb-3">
        <label class="form-label">Generator</label>
        <input type="text" name="text" class="form-control" placeholder="Text Field">
      </div>
      
      <div class="mb-3">
        <label class="form-label">Select one</label>
        <div class="form-selectgroup">
          <label class="form-selectgroup-item">
            <input type="radio" name="f" value="pwdhash" class="form-selectgroup-input" checked>
            <span class="form-selectgroup-label">Password Hash Generator</span>
          </label>
          
          <label class="form-selectgroup-item">
            <input type="radio" name="f" value="totpgen" class="form-selectgroup-input">
            <span class="form-selectgroup-label">TOTP Secret Generator</span>
          </label>
        </div>
      </div>
    </div>
    <div class="card-footer text-end">
      <button type="submit" class="btn btn-primary">Proceed</button>
    </div>
  </div>
</form>
  </div>
</div>
    <?php
    require_once __DIR__ . '/../../../includes/footer.php';
}
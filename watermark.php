<?php
session_start();

// --- 1. Flash Messaging (Handle errors across redirects) ---
$error_message = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_error']); // Clear it so it only shows once

$is_authenticated = isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;

// --- 2. GET Request: Handle Downloads ---
// If the user was redirected here after a successful POST, download the file
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_SESSION['pending_download'])) {
  $download_path = $_SESSION['pending_download'];
  unset($_SESSION['pending_download']); // Clear it so refresh doesn't trigger download again

  if (file_exists($download_path)) {
    // NEW: Set a cookie so JavaScript knows the download is starting
    setcookie('download_token', 'success', time() + 60, '/');

    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($download_path) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($download_path));

    ob_clean();
    flush();
    readfile($download_path);

    @unlink($download_path); // Cleanup after serving
    exit; // Stop execution here so the HTML isn't appended to the file
  }
}

// --- 3. POST Request: Handle Form Submissions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Authentication Check
  if (!$is_authenticated) {
    $password = $_POST['password'] ?? '';
    if (strtolower(trim($password)) === 'rontoshi') {
      $_SESSION['authenticated'] = true;
      $is_authenticated = true;
    } else {
      $_SESSION['flash_error'] = "Authentication failed: Invalid password.";
      header("Location: " . $_SERVER['PHP_SELF']); // Redirect to clear POST
      exit;
    }
  }

  if (isset($_FILES['mediaFile']) && $_FILES['mediaFile']['error'] !== UPLOAD_ERR_NO_FILE) {

    if ($_FILES['mediaFile']['error'] === UPLOAD_ERR_OK) {
      $tmp_file = $_FILES['mediaFile']['tmp_name'];
      $original_name = basename($_FILES['mediaFile']['name']);

      $upload_dir = sys_get_temp_dir() . '/';
      $uploaded_file_path = $upload_dir . uniqid() . '_' . $original_name;

      if (move_uploaded_file($tmp_file, $uploaded_file_path)) {
        $corner = $_POST['corner'] ?? 'none';
        $color = $_POST['color'] ?? '';

        $cmd = './addoverlay.sh ' . escapeshellarg($uploaded_file_path) . ' ' . escapeshellarg($corner);

        if ($corner !== 'none' && !empty($color)) {
          $cmd .= ' ' . escapeshellarg($color);
        }

        $output = [];
        $return_code = 0;
        exec($cmd, $output, $return_code);

        $script_output = trim(implode("\n", $output));

        if ($return_code === 0 && file_exists($script_output)) {
          // SUCCESS: Save path to session and redirect
          $_SESSION['pending_download'] = $script_output;
          @unlink($uploaded_file_path); // Cleanup temp upload
          header("Location: " . $_SERVER['PHP_SELF']);
          exit;
        } else {
          $_SESSION['flash_error'] = "81err:" . htmlspecialchars($script_output);
        }

        @unlink($uploaded_file_path); // Cleanup temp upload on failure
      } else {
        $_SESSION['flash_error'] = "System error: Failed to move uploaded file.";
      }
    } else {
      $_SESSION['flash_error'] = "File upload failed with error code: " . $_FILES['mediaFile']['error'];
    }
  } else {
    $_SESSION['flash_error'] = "Unknown file upload error.";
  }

  // Catch-all redirect for any POST request that didn't already exit
  header("Location: " . $_SERVER['PHP_SELF']);
  exit;
}
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Media Upload with Watermark</title>
  <style>
    body {
      font-family:
        -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica,
        Arial, sans-serif;
      max-width: 600px;
      margin: 40px auto;
      padding: 20px;
      background-color: #f9f9f9;
    }

    .form-container {
      background-color: white;
      padding: 30px;
      border-radius: 8px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .form-group {
      margin-bottom: 25px;
    }

    label.section-label {
      display: block;
      margin-bottom: 10px;
      font-weight: 600;
      color: #333;
    }

    .radio-group {
      display: flex;
      flex-wrap: wrap;
      gap: 15px;
    }

    .radio-label {
      display: flex;
      align-items: center;
      cursor: pointer;
      font-size: 14px;
      color: #555;
    }

    .radio-label input {
      margin-right: 6px;
    }

    input[type="file"] {
      display: block;
      width: 100%;
      padding: 8px;
      font-size: 14px;
      color: #555;
      background-color: #f8f8f8;
      border: 1px solid #ccc;
      border-radius: 4px;
    }

    button[type="submit"] {
      background-color: #007bff;
      color: white;
      border: none;
      padding: 12px 24px;
      font-size: 16px;
      border-radius: 4px;
      cursor: pointer;
      transition: background-color 0.2s;
    }

    button[type="submit"]:hover {
      background-color: #0056b3;
    }

    .disabled-text {
      color: #aaa !important;
    }

    .auth-status {
      color: #28a745;
      font-weight: bold;
      font-size: 14px;
      margin-bottom: 20px;
      display: inline-block;
      padding: 5px 10px;
      background-color: #e6f4ea;
      border-radius: 4px;
    }

    .error-output {
      background-color: rgba(255, 0, 0, .2);
      overflow: auto;
      padding: 8px;
    }
  </style>
</head>

<body>
  <div class="form-container">
    <p>
      This form encodes the uploaded animation as an mp4 file, resizing it to a maximum height of
      720 pixels if necessary, and encoding it for optimal use across social media platforms. Where
      possible, find a suitable watermark position and color to ensure UFD media is branded.
    </p>
    <form id="mediaForm" method="POST" enctype="multipart/form-data">
      <?php if (!$is_authenticated): ?>
        <div class="form-group">
          <label class="section-label" for="password">Password:</label>
          <input type="password" id="password" name="password" required>
        </div>
      <?php else: ?>
        <div class="auth-status">✓ Authenticated</div>
      <?php endif; ?>

      <!-- File Upload -->
      <div class="form-group">
        <label class="section-label" for="mediaFile">Upload any standard GIF/video format:</label>
        <input type="file" id="mediaFile" name="mediaFile" required />
      </div>

      <!-- Watermark Corner -->
      <div class="form-group">
        <label class="section-label">Watermark position:</label>
        <div class="radio-group">
          <label class="radio-label"><input type="radio" name="corner" required value="none" />
            None</label>
          <label class="radio-label"><input type="radio" name="corner" required value="tl" />
            Top-Left</label>
          <label class="radio-label"><input type="radio" name="corner" required value="tr" />
            Top-Right</label>
          <label class="radio-label"><input type="radio" name="corner" required value="bl" />
            Bottom-Left</label>
          <label class="radio-label"><input type="radio" name="corner" required value="br" />
            Bottom-Right</label>
        </div>
      </div>

      <!-- Watermark Color -->
      <div class="form-group">
        <label class="section-label" id="colorLabel">Watermark color:</label>
        <div class="radio-group">
          <label class="radio-label disabled-text" id="label-black">
            <input type="radio" name="color" value="bl" disabled required />
            Black
          </label>
          <label class="radio-label disabled-text" id="label-white">
            <input type="radio" name="color" value="wh" disabled required />
            White
          </label>
        </div>
      </div>

      <p>
        Form processing relies on browser cookies. By using this site, you consent to the use of
        necessary cookies. No tracking data is preserved or shared with any third party.
      </p>

      <!-- Submit -->
      <button type="submit">Submit</button>
    </form>
    <?php if (!empty($error_message)): ?>
      <pre class="error-output">Script Error:
<?php echo $error_message; ?></pre>
    <?php endif; ?>
  </div>

  <script>
    document.addEventListener("DOMContentLoaded", () => {
      const cornerRadios = document.querySelectorAll('input[name="corner"]');
      const colorRadios = document.querySelectorAll('input[name="color"]');
      const labelBlack = document.getElementById("label-black");
      const labelWhite = document.getElementById("label-white");
      const form = document.getElementById('mediaForm');

      // Function to handle enabling/disabling color options
      function updateColorState(selectedValue) {
        const isNone = selectedValue === "none";

        colorRadios.forEach((radio) => {
          radio.disabled = isNone;
          if (isNone) {
            radio.checked = false; // Uncheck if disabled
          }
        });

        // Update styling to reflect disabled state visually
        if (isNone) {
          labelBlack.classList.add("disabled-text");
          labelWhite.classList.add("disabled-text");
        } else {
          labelBlack.classList.remove("disabled-text");
          labelWhite.classList.remove("disabled-text");
        }
      }

      // Add change listener to all corner radio buttons
      cornerRadios.forEach((radio) => {
        radio.addEventListener("change", (e) => {
          updateColorState(e.target.value);
        });
      });

      // Flag to prevent double submissions
      let isSubmitting = false;

      form.addEventListener('submit', (e) => {
        if (isSubmitting) {
          e.preventDefault();
          return;
        }
        isSubmitting = true;

        // Hide any error message; it would be from a from a prior submit
        const errorBox = document.querySelector('.error-output');
        if (errorBox) {
          errorBox.style.display = 'none';
        }

        const submitBtn = form.querySelector('button[type="submit"]');

        if (submitBtn) {
          // Save the original text and update the button
          const originalText = submitBtn.textContent;
          submitBtn.textContent = 'Processing...';

          // Visually disable the button (without actually using the disabled attribute)
          submitBtn.style.opacity = '0.7';
          submitBtn.style.cursor = 'not-allowed';

          // 3. Start polling for the download cookie
          const checkDownload = setInterval(() => {
            if (document.cookie.indexOf('download_token=success') !== -1) {
              clearInterval(checkDownload);

              // Delete the cookie so it's ready for the next upload
              document.cookie = "download_token=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";

              // Reset the button appearance and unlock the form
              submitBtn.textContent = originalText;
              submitBtn.style.opacity = '1';
              submitBtn.style.cursor = 'pointer';
              isSubmitting = false;

              // Reset all form inputs
              form.reset();

              // E. Re-apply the disabled styling to the color radios
              // (Because form.reset() changes values but doesn't trigger 'change' events)
              updateColorState('none');
            }
          }, 250);
        }
      });
    });
  </script>
</body>

</html>
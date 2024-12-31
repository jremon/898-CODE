<?php
header("Expires: 0");
header("Pragma: no-cache");
header("Cache-Control: no-cache, no-store, must-revalidate");

// Use Madrid time
date_default_timezone_set("Europe/Madrid");
$epochMadrid = time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>898.es CODE</title>
  <style>
    html {
      zoom: 1.2;
    }
    body {
      margin: 0;
      padding: 2rem;
      font-family: "Segoe UI", Roboto, sans-serif;
      background: linear-gradient(135deg, #ece9e6, #f6f6f6 50%, #fff);
    }

    .container {
      max-width: 500px;
      margin: 2rem auto;
      padding: 2rem;
      background: linear-gradient(160deg, #ffffff 0%, #fefefe 100%);
      border-radius: 14px;
      box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
      border: 1px solid #e0e0e0;
      transition: all 0.3s ease;
    }
    .container:hover {
      box-shadow: 0 8px 22px rgba(0, 0, 0, 0.15);
    }

    h1 {
      margin-top: 0;
      text-align: center;
      font-size: 1.8rem;
      font-weight: 600;
      color: #333;
      text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
    }

    label {
      display: block;
      margin-top: 1rem;
      font-weight: 600;
      color: #555;
      font-size: 1.05rem;
    }

    input {
      width: 100%;
      margin-top: 0.5rem;
      padding: 0.65rem 0.75rem;
      border: 1px solid #ccc;
      border-radius: 6px;
      font-size: 1rem;
      font-family: inherit;
      box-shadow: inset 0 1px 2px rgba(0,0,0,0.04);
      transition: border 0.2s ease;
    }
    input:focus {
      outline: none;
      border: 1px solid #4CAF50;
    }

    button {
      display: block;
      margin: 2rem auto 0 auto;
      padding: 0.75rem 2.5rem;
      font-size: 1.1rem;
      background: linear-gradient(145deg, #4CAF50, #42a045);
      color: #fff;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      transition: background-color 0.2s ease, transform 0.2s ease;
    }
    button:hover {
      background: linear-gradient(145deg, #45a049, #3e9244);
      transform: translateY(-1px);
    }
    button:active {
      transform: scale(0.98);
    }

    .warning {
      margin-top: 1rem;
      font-size: 0.95rem;
      color: #d9534f;
      text-align: center;
      font-weight: bold;
    }

    .qr-container {
      margin-top: 2rem;
      text-align: center;
    }

    .info-box {
      margin-top: 1rem;
      font-size: 1rem;
      font-weight: 500;
      word-wrap: break-word;
      background-color: #f5f5f5;
      color: #333;
      padding: 1rem;
      border-radius: 6px;
      border: 1px solid #e0e0e0;
      box-shadow: inset 0 1px 3px rgba(0,0,0,0.06);
      transition: background-color 0.3s ease;
    }
    .info-box:hover {
      background-color: #fefefe;
    }

    .totp-code {
      font-size: 1.4rem;
      text-align: center;
      color: #2b2b2b;
      margin-top: 1rem;
      letter-spacing: 0.5px;
    }

    .seed-base64 {
      font-size: 1rem;
      margin-top: 1rem;
      color: #444;
      letter-spacing: 0.15px;
    }
  </style>
  <script src="https://cdn.jsdelivr.net/npm/qrcode@latest/build/qrcode.min.js"></script>
</head>
<body>
  <div class="container">
    <h1>898.es CODE</h1>
    <div class="warning">For optimized security, run this page offline</div>

    <label for="username">User (optional):</label>
    <input type="text" id="username" placeholder="User">

    <label for="serviceName">Service (optional):</label>
    <input type="text" id="serviceName" placeholder="Service">

    <button id="generateButton">Generate TOTP</button>

    <div id="totpDisplay" class="info-box totp-code" style="display:none;"></div>
    <div id="seedDisplay" class="info-box seed-base64" style="display:none;"></div>

    <div id="qrContainer" class="qr-container"></div>
  </div>

  <script>
    const epochMadrid = <?php echo $epochMadrid; ?>;

    function generateSecretBase64() {
      const randomBytes = new Uint8Array(64);
      crypto.getRandomValues(randomBytes);
      return btoa(String.fromCharCode(...randomBytes));
    }

    async function calculateTOTP(secretBase64, epochSeconds) {
      const keyData = Uint8Array.from(atob(secretBase64), c => c.charCodeAt(0));
      const key = await crypto.subtle.importKey(
        "raw",
        keyData,
        { name: "HMAC", hash: { name: "SHA-512" } },
        false,
        ["sign"]
      );

      const period = 120;
      const counterValue = Math.floor(epochSeconds / period);

      const counterBuffer = new ArrayBuffer(8);
      const counterView = new DataView(counterBuffer);
      counterView.setUint32(4, counterValue, false);

      const signature = await crypto.subtle.sign("HMAC", key, counterBuffer);
      const signatureBytes = new Uint8Array(signature);

      const offset = signatureBytes[signatureBytes.length - 1] & 0x0f;
      const binary =
        ((signatureBytes[offset] & 0x7f) << 24) |
        ((signatureBytes[offset + 1] & 0xff) << 16) |
        ((signatureBytes[offset + 2] & 0xff) << 8) |
        (signatureBytes[offset + 3] & 0xff);

      const totp = binary % 1_000_000;
      return totp.toString().padStart(6, "0");
    }

    async function generateTOTP() {
      const secret = generateSecretBase64();

      // Show the seed (Base64)
      const seedDisplay = document.getElementById("seedDisplay");
      seedDisplay.textContent = "Seed (Base64): " + secret;
      seedDisplay.style.display = "block";

      // Calculate TOTP using server epoch time
      const totpCode = await calculateTOTP(secret, epochMadrid);
      const totpDisplay = document.getElementById("totpDisplay");
      totpDisplay.textContent = "TOTP (120s): " + totpCode;
      totpDisplay.style.display = "block";

      // Prepare the data string for QR
      const userValue = document.getElementById("username").value.trim() || "User";
      const serviceValue = document.getElementById("serviceName").value.trim() || "Service";
      const dataString = `seed://${encodeURIComponent(serviceValue)}:${encodeURIComponent(userValue)}?seed=${secret}`;

      // Generate the QR code
      const qrContainer = document.getElementById("qrContainer");
      qrContainer.innerHTML = "";
      QRCode.toCanvas(
        dataString,
        { errorCorrectionLevel: "H" },
        (err, canvas) => {
          if (err) {
            console.error("Error generating QR:", err);
            return;
          }
          qrContainer.appendChild(canvas);
        }
      );
    }

    document.addEventListener("DOMContentLoaded", () => {
      const button = document.getElementById("generateButton");
      button.addEventListener("click", generateTOTP);
    });
  </script>
</body>
</html>
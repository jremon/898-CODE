<?php
header("Expires: 0");
header("Pragma: no-cache");
header("Cache-Control: no-cache, no-store, must-revalidate");
date_default_timezone_set("Europe/Madrid");
$epochMadrid = time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>898 CODE</title>
  <style>
    html { zoom: 1.2; }
    body {
      margin: 0;
      padding: 2rem;
      font-family: "Segoe UI", Roboto, sans-serif;
      background: linear-gradient(135deg, #ece9e6, #f6f6f6 50%, #fff);
      position: relative;
    }
    .corner-note {
      position: fixed;
      top: 1rem;
      right: 1rem;
      background: gold;
      color: #333;
      padding: 0.7rem 1.2rem;
      border-radius: 14px;
      box-shadow: 0 4px 8px rgba(0,0,0,0.15);
      font-size: 0.95rem;
      font-weight: 600;
      z-index: 9999;
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
    .warning-red {
      text-align: center;
      color: #d9534f;
      font-weight: bold;
      margin-bottom: 1rem;
      font-size: 1rem;
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
  <div class="corner-note">🔒 No data leaves your browser</div>
  <div class="container">
    <h1>898 CODE</h1>
    <div class="warning-red">For best security, run this page offline</div>

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
    // Current epoch from PHP (Madrid time)
    const epochMadrid = <?php echo $epochMadrid; ?>;

    // Generate a random 20-byte secret
    function generateSecretBytes() {
      const randomBytes = new Uint8Array(20);
      crypto.getRandomValues(randomBytes);
      return randomBytes;
    }

    // Convert raw bytes to Base32 (for the otpauth URI)
    function bytesToBase32(uint8Arr) {
      const alphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ234567";
      let bits = 0, value = 0, output = "";
      for (let i = 0; i < uint8Arr.length; i++) {
        value = (value << 8) | uint8Arr[i];
        bits += 8;
        while (bits >= 5) {
          output += alphabet[(value >>> (bits - 5)) & 31];
          bits -= 5;
        }
      }
      if (bits > 0) {
        output += alphabet[(value << (5 - bits)) & 31];
      }
      return output;
    }

    // Calculate TOTP using HMAC-SHA1, 30s steps, 6 digits
    async function calculateTOTP(secretBytes, currentEpoch) {
      const period = 30;
      const digits = 6;
      // The TOTP "counter" = floor(epoch / period)
      const counterValue = Math.floor(currentEpoch / period);

      // Prepare the counter as an 8-byte array
      const counterBuffer = new ArrayBuffer(8);
      const counterView = new DataView(counterBuffer);
      counterView.setUint32(4, counterValue, false);

      // Import the key for HMAC-SHA1
      const key = await crypto.subtle.importKey(
        "raw",
        secretBytes,
        { name: "HMAC", hash: { name: "SHA-1" } },
        false,
        ["sign"]
      );
      // Calculate HMAC
      const signature = await crypto.subtle.sign("HMAC", key, counterBuffer);
      const signatureBytes = new Uint8Array(signature);

      // Dynamic truncation
      const offset = signatureBytes[signatureBytes.length - 1] & 0x0f;
      const binary =
        ((signatureBytes[offset] & 0x7f) << 24) |
        ((signatureBytes[offset + 1] & 0xff) << 16) |
        ((signatureBytes[offset + 2] & 0xff) << 8)  |
        (signatureBytes[offset + 3] & 0xff);

      const totp = binary % (10 ** digits);
      return totp.toString().padStart(digits, "0");
    }

    async function generateTOTP() {
      // 1) Generate a 20-byte secret
      const secretBytes = generateSecretBytes();

      // 2) Convert to Base32
      const secretBase32 = bytesToBase32(secretBytes);

      // 3) Show that seed in the UI (matching the QR code)
      const seedDisplay = document.getElementById("seedDisplay");
      seedDisplay.textContent = "Seed (20 bytes, Base32): " + secretBase32;
      seedDisplay.style.display = "block";

      // 4) Calculate TOTP at current epoch
      const totpCode = await calculateTOTP(secretBytes, epochMadrid);

      // 5) Display TOTP code
      const totpDisplay = document.getElementById("totpDisplay");
      totpDisplay.textContent = "TOTP (30s, 6 digits, SHA1): " + totpCode;
      totpDisplay.style.display = "block";

      // 6) Build the standard otpauth:// URL
      const userValue = document.getElementById("username").value.trim() || "User";
      const serviceValue = document.getElementById("serviceName").value.trim() || "Service";

      const otpauthUrl =
        "otpauth://totp/" +
        encodeURIComponent(userValue) +
        "?secret=" + secretBase32 +
        "&issuer=" + encodeURIComponent(serviceValue) +
        "&digits=6&period=30";

      // 7) Render the QR code
      const qrContainer = document.getElementById("qrContainer");
      qrContainer.innerHTML = "";
      QRCode.toCanvas(otpauthUrl, { errorCorrectionLevel: "H" }, function(err, canvas) {
        if (err) {
          console.error("Error generating QR:", err);
          return;
        }
        qrContainer.appendChild(canvas);
      });
    }

    document.addEventListener("DOMContentLoaded", function() {
      const button = document.getElementById("generateButton");
      button.addEventListener("click", generateTOTP);
    });
  </script>

  <div class="banner-container" style="text-align: center; margin-top: 2rem;">
    <a href="https://github.com/jremon/898-CODE/blob/main/code.php"
       target="_blank"
       style="text-decoration: none; margin-right: 10px;">
      <img src="https://img.shields.io/badge/GitHub-898--CODE-blue?logo=github"
           alt="View on GitHub"
           style="max-width: 200px; height: auto; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: transform 0.3s;">
    </a>
    <a href="https://opensource.org/licenses/MIT"
       target="_blank"
       style="text-decoration: none; margin-left: 10px;">
      <img src="https://img.shields.io/badge/License-MIT-green?logo=opensourceinitiative"
           alt="MIT License"
           style="max-width: 200px; height: auto; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: transform 0.3s;">
    </a>
  </div>

  <style>
    .banner-container a img:hover {
      transform: translateY(-2px) scale(1.02);
    }
  </style>

  <script>
    // Block network calls for privacy
    window.fetch = function() {
      console.log("fetch blocked");
      return Promise.reject(new Error("fetch blocked"));
    };
    (function() {
      let OldXHR = window.XMLHttpRequest;
      function NewXHR() {
        throw new Error("Blocked XMLHttpRequest");
      }
      window.XMLHttpRequest = NewXHR;
    })();
    document.addEventListener("submit", function(e) {
      e.preventDefault();
      console.log("form submit blocked");
    }, true);
    navigator.sendBeacon = function() {
      console.log("sendBeacon blocked");
      return false;
    };
    window.WebSocket = function() {
      throw new Error("Blocked WebSocket");
    };
    if (XMLHttpRequest.prototype.open) {
      XMLHttpRequest.prototype.open = function() {
        throw new Error("Blocked XHR open");
      };
    }
    if (XMLHttpRequest.prototype.send) {
      XMLHttpRequest.prototype.send = function() {
        throw new Error("Blocked XHR send");
      };
    }
  </script>
</body>
</html>
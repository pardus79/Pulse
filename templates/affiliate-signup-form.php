<form id="affiliate-signup-form">
    <label for="lightning-address">Lightning Address:</label>
    <input type="text" id="lightning-address" name="lightning-address" required>
    <div id="address-error" class="error-message"></div>
    <button type="submit">Generate Affiliate Link</button>
</form>

<div id="public-key-info">
    <h3>Public Encryption Key:</h3>
    <pre id="public-key"></pre>
    <p>You can use this public key to independently verify your affiliate link.</p>
</div>

<script>
document.getElementById('affiliate-signup-form').addEventListener('submit', function(e) {
    e.preventDefault();
    var address = document.getElementById('lightning-address').value;
    var errorDiv = document.getElementById('address-error');
    
    if (!validateLightningAddress(address)) {
        errorDiv.textContent = 'Invalid Lightning address format. Please use a valid email-like format.';
        return;
    }
    
    // If valid, proceed with form submission (encrypt address and generate link)
    this.submit();
});

function validateLightningAddress(address) {
    var regex = /^[a-zA-Z0-9.!#$%&'*+\/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/;
    return regex.test(address);
}

// Fetch and display public key
fetch('/wp-json/my-affiliate-plugin/v1/public-key')
    .then(response => response.text())
    .then(key => {
        document.getElementById('public-key').textContent = key;
    });
</script>
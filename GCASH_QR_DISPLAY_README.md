# GCash QR Code Display in Host Payments

## Overview
When an admin selects a live seller to send payment to, the system now automatically displays the seller's GCash QR code for easy reference.

## Features

### 1. **Automatic QR Code Display**
- When a live seller is selected from the dropdown, their GCash QR code is automatically displayed
- Shows below the GCash account information
- Styled with a gradient purple background for visibility

### 2. **QR Code Preview**
- QR code is displayed at 200x200px maximum size
- Centered display with white border and shadow
- Hover effect: scales up slightly for better visibility
- Click-to-enlarge functionality

### 3. **Modal Viewing**
- Click on the QR code thumbnail to view it in full size
- Opens in the same modal used for viewing payment receipts
- Modal title shows "GCash QR Code"
- Can be closed by clicking the X button or clicking outside

### 4. **User Experience**
- QR code only shows for users who have uploaded their GCash QR code
- Seamlessly integrates with existing form layout
- Provides quick visual confirmation of payment destination
- No need to navigate to separate GCash QR page

## Technical Implementation

### Database Changes
**SQL Query Updated:**
```sql
SELECT 
    u.id,
    u.full_name,
    u.username,
    u.experienced_status,
    g.gcash_number,
    g.gcash_name,
    g.qr_code_image  -- ADDED
FROM users u
INNER JOIN gcash_qr_codes g ON u.id = g.user_id AND g.is_active = 1
WHERE u.role = 'live_seller' AND u.status = 'active'
ORDER BY u.full_name ASC
```

### HTML Structure
```html
<select id="user_id" name="user_id" required onchange="updateUserInfo()">
    <option value="">-- Select Live Seller --</option>
    <option value="1" 
            data-gcash="09123456789"
            data-name="John Doe"
            data-qr="gcash_1_1234567890.jpg">  <!-- ADDED -->
        John Doe (@johndoe)
    </option>
</select>

<!-- GCash Info Display -->
<div id="userGcashInfo" class="gcash-info" style="display: none;">
    <small>GCash: <strong id="gcashNumber"></strong> - <strong id="gcashName"></strong></small>
</div>

<!-- NEW: QR Code Preview -->
<div id="qrCodePreview" class="qr-code-preview" style="display: none;">
    <div class="qr-code-label">GCash QR Code:</div>
    <img id="qrCodeImage" src="" alt="GCash QR Code" onclick="viewQRCode()">
</div>
```

### JavaScript Functions

#### Updated `updateUserInfo()`
```javascript
function updateUserInfo() {
    const select = document.getElementById('user_id');
    const selectedOption = select.options[select.selectedIndex];
    const gcashInfo = document.getElementById('userGcashInfo');
    const qrCodePreview = document.getElementById('qrCodePreview');
    const qrCodeImage = document.getElementById('qrCodeImage');
    
    if (selectedOption.value) {
        const gcashNumber = selectedOption.getAttribute('data-gcash');
        const gcashName = selectedOption.getAttribute('data-name');
        const qrCodePath = selectedOption.getAttribute('data-qr');  // NEW
        
        document.getElementById('gcashNumber').textContent = gcashNumber;
        document.getElementById('gcashName').textContent = gcashName;
        gcashInfo.style.display = 'block';
        
        // NEW: Show QR code if available
        if (qrCodePath) {
            qrCodeImage.src = '../uploads/gcash/' + qrCodePath;
            qrCodePreview.style.display = 'block';
        } else {
            qrCodePreview.style.display = 'none';
        }
    } else {
        gcashInfo.style.display = 'none';
        qrCodePreview.style.display = 'none';  // NEW
    }
}
```

#### New `viewQRCode()` Function
```javascript
function viewQRCode() {
    const qrCodeImage = document.getElementById('qrCodeImage');
    const modal = document.getElementById('receiptModal');
    const modalImg = document.getElementById('modalImage');
    const modalTitle = document.getElementById('modalTitle');
    
    modal.style.display = 'block';
    modalImg.src = qrCodeImage.src;
    modalTitle.textContent = 'GCash QR Code';
}
```

### CSS Styling
```css
.qr-code-preview {
    margin-top: 1rem;
    padding: 1rem;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 12px;
    text-align: center;
}

.qr-code-label {
    color: white;
    font-weight: 600;
    margin-bottom: 0.75rem;
    font-size: 0.95rem;
}

.qr-code-preview img {
    max-width: 200px;
    max-height: 200px;
    border-radius: 8px;
    border: 3px solid white;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    cursor: pointer;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    background: white;
}

.qr-code-preview img:hover {
    transform: scale(1.05);
    box-shadow: 0 8px 12px rgba(0, 0, 0, 0.2);
}
```

## User Flow

1. **Admin selects a live seller** from the dropdown in Host Payments page
2. **System displays:**
   - GCash account number and name (existing)
   - GCash QR code thumbnail (NEW)
3. **Admin can:**
   - View QR code directly in the form
   - Click to enlarge QR code in modal
   - Proceed to upload payment receipt
4. **Benefits:**
   - Quick reference to payment destination
   - Visual confirmation before sending payment
   - No need to switch between pages

## Files Modified

- **admin/host-payments.php**
  - Updated SQL query to include `qr_code_image`
  - Added `data-qr` attribute to select options
  - Added QR code preview HTML section
  - Enhanced `updateUserInfo()` JavaScript function
  - Added `viewQRCode()` JavaScript function
  - Added CSS styling for `.qr-code-preview`

## Notes

- QR code images are stored in `uploads/gcash/` directory
- Only users with active GCash QR codes are shown in the dropdown
- QR code preview uses the same modal as payment receipt viewing
- Responsive design ensures QR code displays properly on all devices
- Hover and click interactions provide intuitive user experience

## Future Enhancements

Potential improvements:
- Add QR code download button
- Show upload date of QR code
- Add "outdated" indicator for old QR codes
- Allow admin to flag QR codes for user update

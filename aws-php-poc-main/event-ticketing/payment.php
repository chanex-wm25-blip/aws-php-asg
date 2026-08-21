<?php
require 'config.php';
require 'auth.php';
require 'helpers.php';
require_login();

$uid = current_user_id();
$error = '';

$event_id = (int)($_POST['event_id'] ?? 0);
$seatIds = array_values(array_unique(array_map('intval', $_POST['seat_ids'] ?? [])));
$confirmed = isset($_POST['confirm']);
$payment_method = $_POST['payment_method'] ?? 'card';

if ($event_id < 1) {
    header('Location: create.php');
    exit;
}

$stmt = $conn->prepare('SELECT * FROM events WHERE id = ?');
$stmt->bind_param('i', $event_id);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$event) {
    die('Event not found.');
}

$isSeated = (bool)$event['has_seating'];
$seatDetails = [];

if ($isSeated) {
    if (empty($seatIds)) {
        header('Location: seat_select.php?event_id=' . $event_id);
        exit;
    }
    $placeholders = implode(',', array_fill(0, count($seatIds), '?'));
    $stmt = $conn->prepare("SELECT id, row_label, seat_number, is_booked FROM seats WHERE event_id = ? AND id IN ($placeholders)");
    $stmt->bind_param(str_repeat('i', count($seatIds) + 1), $event_id, ...$seatIds);
    $stmt->execute();
    $seatDetails = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $quantity = count($seatDetails);
    if ($quantity !== count($seatIds) || $quantity < 1) {
        $error = 'One or more selected seats are invalid.';
    } elseif (array_filter($seatDetails, fn($s) => $s['is_booked'])) {
        $error = 'One or more selected seats have just been taken by someone else. Please choose again.';
    }
} else {
    $quantity = (int)($_POST['quantity'] ?? 0);
    if ($quantity < 1) {
        header('Location: create.php');
        exit;
    }
}

$remaining = $event['total_tickets'] - $event['tickets_sold'];
$total_price = $event['ticket_price'] * $quantity;

if (!$isSeated && $quantity > $remaining) {
    $error = 'Not enough tickets remaining for this event.';
}

if ($confirmed && !$error) {
    // Payment details are validated for format only - this is a simulated checkout for a
    // teaching project, not a real payment gateway integration. Card/bank details are
    // never stored: they're used for this request only and discarded once validated.
    if ($payment_method === 'fpx') {
        $bank = trim($_POST['bank'] ?? '');
        if ($bank === '') {
            $error = 'Please select your bank.';
        }
    } else {
        $card_name   = trim($_POST['card_name'] ?? '');
        $card_number = preg_replace('/\s+/', '', $_POST['card_number'] ?? '');
        $card_expiry = trim($_POST['card_expiry'] ?? '');
        $card_cvv    = trim($_POST['card_cvv'] ?? '');

        if ($card_name === '') {
            $error = 'Please enter the name on the card.';
        } elseif (!preg_match('/^\d{13,19}$/', $card_number)) {
            $error = 'Card number must be 13-19 digits.';
        } elseif (!preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $card_expiry)) {
            $error = 'Expiry must be in MM/YY format.';
        } elseif (!preg_match('/^\d{3,4}$/', $card_cvv)) {
            $error = 'CVV must be 3 or 4 digits.';
        }
    }

    if (!$error) {
        $conn->begin_transaction();

        $stmt = $conn->prepare('SELECT ticket_price, total_tickets, tickets_sold FROM events WHERE id = ? FOR UPDATE');
        $stmt->bind_param('i', $event_id);
        $stmt->execute();
        $lockedEvent = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $lockedSeatIds = [];
        if ($isSeated) {
            $placeholders = implode(',', array_fill(0, count($seatIds), '?'));
            $stmt = $conn->prepare("SELECT id FROM seats WHERE event_id = ? AND is_booked = 0 AND id IN ($placeholders) FOR UPDATE");
            $stmt->bind_param(str_repeat('i', count($seatIds) + 1), $event_id, ...$seatIds);
            $stmt->execute();
            $lockedSeatIds = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'id');
            $stmt->close();
        }

        if ($isSeated && count($lockedSeatIds) !== count($seatIds)) {
            $error = 'One or more selected seats have just been taken by someone else. Please choose again.';
            $conn->rollback();
        } elseif (!$isSeated && $lockedEvent['tickets_sold'] + $quantity > $lockedEvent['total_tickets']) {
            $error = 'Not enough tickets remaining for this event.';
            $conn->rollback();
        } else {
            $total_price = $lockedEvent['ticket_price'] * $quantity;

            $stmt = $conn->prepare('INSERT INTO orders (user_id, event_id, quantity, total_price) VALUES (?, ?, ?, ?)');
            $stmt->bind_param('iiid', $uid, $event_id, $quantity, $total_price);
            $stmt->execute();
            $orderId = $stmt->insert_id;
            $stmt->close();

            $stmt = $conn->prepare('UPDATE events SET tickets_sold = tickets_sold + ? WHERE id = ?');
            $stmt->bind_param('ii', $quantity, $event_id);
            $stmt->execute();
            $stmt->close();

            $ticketStmt = $conn->prepare('INSERT INTO tickets (order_id, seat_id, qr_token) VALUES (?, ?, ?)');
            if ($isSeated) {
                $markSeatStmt = $conn->prepare('UPDATE seats SET is_booked = 1 WHERE id = ?');
                foreach ($lockedSeatIds as $seatId) {
                    $markSeatStmt->bind_param('i', $seatId);
                    $markSeatStmt->execute();

                    $token = generate_qr_token();
                    $ticketStmt->bind_param('iis', $orderId, $seatId, $token);
                    $ticketStmt->execute();
                }
                $markSeatStmt->close();
            } else {
                $nullSeatId = null;
                for ($i = 0; $i < $quantity; $i++) {
                    $token = generate_qr_token();
                    $ticketStmt->bind_param('iis', $orderId, $nullSeatId, $token);
                    $ticketStmt->execute();
                }
            }
            $ticketStmt->close();

            $conn->commit();
            header('Location: confirmation.php?id=' . $orderId);
            exit;
        }
    }
}

$pageTitle = 'Payment';
require 'partials/header.php';
?>
<div class="form-card" style="max-width:480px;">
<h1>Payment</h1>
<?php if ($error): ?><p class="alert alert-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>

<div class="order-summary">
<div class="order-summary-row"><span><?= htmlspecialchars($event['event_name']) ?></span><span><?= htmlspecialchars(date('d M Y', strtotime($event['event_date']))) ?></span></div>
<div class="order-summary-row"><span>Ticket price</span><span>RM<?= number_format($event['ticket_price'], 2) ?></span></div>
<?php if ($isSeated): ?>
<div class="order-summary-row"><span>Seats</span><span><?php
$seatLabels = array_map(fn($s) => $s['row_label'] . $s['seat_number'], $seatDetails);
echo htmlspecialchars(implode(', ', $seatLabels));
?></span></div>
<?php else: ?>
<div class="order-summary-row"><span>Quantity</span><span>&times; <?= (int)$quantity ?></span></div>
<?php endif; ?>
<div class="order-summary-row total"><span>Total</span><span>RM<?= number_format($total_price, 2) ?></span></div>
</div>

<form method="post" action="payment.php" id="payment-form">
<input type="hidden" name="event_id" value="<?= (int)$event_id ?>">
<?php if ($isSeated): ?>
<?php foreach ($seatIds as $sid): ?>
<input type="hidden" name="seat_ids[]" value="<?= (int)$sid ?>">
<?php endforeach; ?>
<?php else: ?>
<input type="hidden" name="quantity" value="<?= (int)$quantity ?>">
<?php endif; ?>
<input type="hidden" name="confirm" value="1">

<div class="payment-methods">
<label class="payment-method-option"><input type="radio" name="payment_method" value="card" <?= $payment_method !== 'fpx' ? 'checked' : '' ?>> Credit / Debit Card</label>
<label class="payment-method-option"><input type="radio" name="payment_method" value="fpx" <?= $payment_method === 'fpx' ? 'checked' : '' ?>> Online Banking (FPX)</label>
</div>

<div id="card-fields">
<label>Name on Card <input type="text" name="card_name" placeholder="e.g. TAN AH KOW"></label>
<label>Card Number <input type="text" name="card_number" placeholder="4111 1111 1111 1111" maxlength="19"></label>
<div class="card-row">
<label>Expiry (MM/YY) <input type="text" name="card_expiry" placeholder="12/28" maxlength="5"></label>
<label>CVV <input type="text" name="card_cvv" placeholder="123" maxlength="4"></label>
</div>
</div>

<div id="fpx-fields">
<label>Select Bank
<select name="bank">
<option value="">-- Choose your bank --</option>
<option value="maybank2u">Maybank2u</option>
<option value="cimb_clicks">CIMB Clicks</option>
<option value="public_bank_pbe">Public Bank PBe</option>
<option value="rhb_now">RHB Now</option>
<option value="hong_leong_connect">Hong Leong Connect</option>
<option value="bank_islam">Bank Islam Go Online</option>
</select>
</label>
</div>

<button type="submit">Pay RM<?= number_format($total_price, 2) ?></button>
</form>
<p class="payment-note">This is a simulated checkout for a class project - no real payment is processed and no card or bank login details are stored.</p>
</div>
<script>
(function () {
    var cardRadio = document.querySelector('input[name="payment_method"][value="card"]');
    var fpxRadio = document.querySelector('input[name="payment_method"][value="fpx"]');
    var cardFields = document.getElementById('card-fields');
    var fpxFields = document.getElementById('fpx-fields');
    if (!cardRadio || !fpxRadio || !cardFields || !fpxFields) return;

    function update() {
        var isCard = cardRadio.checked;
        cardFields.style.display = isCard ? '' : 'none';
        fpxFields.style.display = isCard ? 'none' : '';
        cardFields.querySelectorAll('input').forEach(function (el) { el.required = isCard; });
        fpxFields.querySelectorAll('select').forEach(function (el) { el.required = !isCard; });
    }

    cardRadio.addEventListener('change', update);
    fpxRadio.addEventListener('change', update);
    update();
})();
</script>
<?php require 'partials/footer.php'; ?>

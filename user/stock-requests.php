<?php
// stock-requests.php
// Lists stock requests and allows approve/reject/complete/delete and export CSV

include('config/config.php');
include('includes/auth-check.php');

// Access control: allow lineman and admins to view; only admin/super_admin can approve/reject/complete/delete
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'lineman';
$user_name = $_SESSION['name'] ?? 'User';

// Messages
$success_message = '';
$error_message = '';

// Helper: safe int
function safe_int($v) { return intval($v); }

// Handle Export CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=stock_requests_' . date('Ymd_His') . '.csv');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Request ID','Product','Product Code','Requested Qty','Current Qty','Priority','Status','Requested By','Requested At','Approved By','Approved At','Notes']);

    $sql = "SELECT sr.*, p.product_name, p.product_code, l.full_name as requester_name, a.full_name as approver_name
            FROM stock_requests sr
            LEFT JOIN products p ON sr.product_id = p.id
            LEFT JOIN linemen l ON sr.requested_by = l.id
            LEFT JOIN linemen a ON sr.approved_by = a.id
            ORDER BY sr.created_at DESC";
    $res = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($res)) {
        fputcsv($out, [
            $row['request_id'],
            $row['product_name'] ?? '',
            $row['product_code'] ?? '',
            $row['requested_qty'],
            $row['current_qty'],
            $row['priority'],
            $row['status'],
            $row['requester_name'] ?? '',
            $row['created_at'],
            $row['approver_name'] ?? '',
            $row['approved_at'] ?? '',
            $row['notes'] ?? ''
        ]);
    }
    fclose($out);
    exit;
}

// Handle POST actions: approve, reject, complete, delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // APPROVE
    if (isset($_POST['action']) && $_POST['action'] === 'approve' && ($user_role === 'admin' || $user_role === 'super_admin')) {
        $sr_id = safe_int($_POST['sr_id']);
        $admin_note = mysqli_real_escape_string($conn, $_POST['admin_note'] ?? '');

        mysqli_begin_transaction($conn);
        try {
            // fetch request and product
            $q = "SELECT * FROM stock_requests WHERE id = $sr_id FOR UPDATE";
            $rq = mysqli_query($conn, $q);
            if (!$rq || mysqli_num_rows($rq) === 0) throw new Exception("Stock request not found.");
            $sr = mysqli_fetch_assoc($rq);
            if ($sr['status'] !== 'pending') throw new Exception("Only pending requests can be approved.");

            $product_id = intval($sr['product_id']);
            $req_qty = intval($sr['requested_qty']);

            // update product quantity (lock product row)
            $pq = "SELECT quantity, stock_price FROM products WHERE id = $product_id FOR UPDATE";
            $pr = mysqli_query($conn, $pq);
            if (!$pr || mysqli_num_rows($pr) === 0) throw new Exception("Product not found for this request.");
            $p = mysqli_fetch_assoc($pr);
            $prev_qty = intval($p['quantity']);
            $new_qty = $prev_qty + $req_qty;
            $stock_price = floatval($p['stock_price'] ?? 0);

            $u1 = "UPDATE products SET quantity = $new_qty, updated_at = NOW() WHERE id = $product_id";
            if (!mysqli_query($conn, $u1)) throw new Exception("Failed to update product quantity: " . mysqli_error($conn));

            // insert into stock_transactions
            $note = mysqli_real_escape_string($conn, trim(($sr['notes'] ?? '') . ($admin_note ? " | Admin: $admin_note" : "")));
            $t_sql = "INSERT INTO stock_transactions 
                (product_id, transaction_type, quantity, stock_price, previous_quantity, new_quantity, notes, created_by, created_at)
                VALUES ($product_id, 'purchase', $req_qty, $stock_price, $prev_qty, $new_qty, '$note', $user_id, NOW())";
            if (!mysqli_query($conn, $t_sql)) throw new Exception("Failed to insert stock transaction: " . mysqli_error($conn));

            // update stock_requests
            $a_sql = "UPDATE stock_requests SET status = 'approved', approved_by = $user_id, approved_at = NOW(), updated_at = NOW(), notes = '" . mysqli_real_escape_string($conn, $sr['notes'] . ($admin_note ? " | Admin: $admin_note" : "")) . "' WHERE id = $sr_id";
            if (!mysqli_query($conn, $a_sql)) throw new Exception("Failed to update stock request: " . mysqli_error($conn));

            mysqli_commit($conn);
            $success_message = "Request {$sr['request_id']} approved and product stock updated (+$req_qty).";
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error_message = $e->getMessage();
        }
    }

    // REJECT
    if (isset($_POST['action']) && $_POST['action'] === 'reject' && ($user_role === 'admin' || $user_role === 'super_admin')) {
        $sr_id = safe_int($_POST['sr_id']);
        $admin_note = mysqli_real_escape_string($conn, $_POST['admin_note'] ?? '');
        $sql = "SELECT * FROM stock_requests WHERE id = $sr_id";
        $res = mysqli_query($conn, $sql);
        if ($res && mysqli_num_rows($res) > 0) {
            $row = mysqli_fetch_assoc($res);
            if ($row['status'] === 'pending') {
                $notes = mysqli_real_escape_string($conn, trim(($row['notes'] ?? '') . ($admin_note ? " | Rejected: $admin_note" : " | Rejected")));
                $u = "UPDATE stock_requests SET status = 'rejected', updated_at = NOW(), notes = '$notes' WHERE id = $sr_id";
                if (mysqli_query($conn, $u)) {
                    $success_message = "Request {$row['request_id']} rejected.";
                } else {
                    $error_message = "Failed to reject request: " . mysqli_error($conn);
                }
            } else {
                $error_message = "Only pending requests can be rejected.";
            }
        } else {
            $error_message = "Request not found.";
        }
    }

    // COMPLETE (mark as completed) - admin or lineman who requested can mark completed
    if (isset($_POST['action']) && $_POST['action'] === 'complete') {
        $sr_id = safe_int($_POST['sr_id']);
        $sql = "SELECT * FROM stock_requests WHERE id = $sr_id";
        $res = mysqli_query($conn, $sql);
        if ($res && mysqli_num_rows($res) > 0) {
            $row = mysqli_fetch_assoc($res);
            // allow if admin or requester
            if ($user_role === 'admin' || $user_role === 'super_admin' || intval($row['requested_by']) === intval($user_id)) {
                if ($row['status'] === 'approved' || $row['status'] === 'pending') {
                    $u = "UPDATE stock_requests SET status = 'completed', updated_at = NOW() WHERE id = $sr_id";
                    if (mysqli_query($conn, $u)) {
                        $success_message = "Request {$row['request_id']} marked as completed.";
                    } else {
                        $error_message = "Failed to mark as completed: " . mysqli_error($conn);
                    }
                } else {
                    $error_message = "Only pending/approved requests can be completed.";
                }
            } else {
                $error_message = "You are not authorized to complete this request.";
            }
        } else {
            $error_message = "Request not found.";
        }
    }

    // DELETE (only super_admin)
    if (isset($_POST['action']) && $_POST['action'] === 'delete' && $user_role === 'super_admin') {
        $sr_id = safe_int($_POST['sr_id']);
        $sql = "SELECT * FROM stock_requests WHERE id = $sr_id";
        $res = mysqli_query($conn, $sql);
        if ($res && mysqli_num_rows($res) > 0) {
            $row = mysqli_fetch_assoc($res);
            $d = "DELETE FROM stock_requests WHERE id = $sr_id";
            if (mysqli_query($conn, $d)) {
                $success_message = "Request {$row['request_id']} deleted.";
            } else {
                $error_message = "Failed to delete request: " . mysqli_error($conn);
            }
        } else {
            $error_message = "Request not found.";
        }
    }
}

// Fetch requests list
$list_sql = "SELECT sr.*, p.product_name, p.product_code, l.full_name as requester_name, a.full_name as approver_name
             FROM stock_requests sr
             LEFT JOIN products p ON sr.product_id = p.id
             LEFT JOIN linemen l ON sr.requested_by = l.id
             LEFT JOIN linemen a ON sr.approved_by = a.id
             ORDER BY sr.created_at DESC";
$list_res = mysqli_query($conn, $list_sql);
?>

<!doctype html>
<html lang="en">
<?php include('includes/head.php'); ?>
<body data-sidebar="dark">
    <?php include('includes/pre-loader.php'); ?>
    <div id="layout-wrapper">
        <?php include('includes/topbar.php'); ?>
        <div class="vertical-menu">
            <div data-simplebar class="h-100">
                <?php 
                $current_page = 'stock-requests';
                include('includes/sidebar.php');
                ?>
            </div>
        </div>

        <div class="main-content">
            <div class="page-content">
                <div class="container-fluid">

                    <!-- Messages -->
                    <?php if ($success_message): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="mdi mdi-check-all me-2"></i> <?php echo $success_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="mdi mdi-alert-circle-outline me-2"></i> <?php echo $error_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h4 class="card-title">Stock Requests</h4>
                            <p class="card-title-desc">View and manage stock requests submitted by linemen.</p>
                        </div>
                        <div class="col-md-6 text-end">
                            <a href="available-stock.php" class="btn btn-light"><i class="mdi mdi-arrow-left me-1"></i> Back to Stock</a>
                            <a href="?export=csv" class="btn btn-outline-secondary"><i class="mdi mdi-download me-1"></i> Export CSV</a>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Request ID</th>
                                            <th>Product</th>
                                            <th class="text-center">Requested Qty</th>
                                            <th class="text-center">Current Qty</th>
                                            <th>Priority</th>
                                            <th>Requested By</th>
                                            <th>Requested At</th>
                                            <th>Status</th>
                                            <th>Approved By</th>
                                            <th>Approved At</th>
                                            <th class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($list_res && mysqli_num_rows($list_res) > 0): ?>
                                            <?php while ($row = mysqli_fetch_assoc($list_res)): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($row['request_id']); ?></td>
                                                    <td>
                                                        <div><strong><?php echo htmlspecialchars($row['product_name'] ?? '—'); ?></strong></div>
                                                        <small class="text-muted"><?php echo htmlspecialchars($row['product_code'] ?? ''); ?></small>
                                                    </td>
                                                    <td class="text-center"><?php echo intval($row['requested_qty']); ?></td>
                                                    <td class="text-center"><?php echo intval($row['current_qty']); ?></td>
                                                    <td>
                                                        <?php
                                                            $pclass = 'badge bg-secondary';
                                                            if ($row['priority'] === 'urgent') $pclass = 'badge bg-danger';
                                                            elseif ($row['priority'] === 'high') $pclass = 'badge bg-warning';
                                                            elseif ($row['priority'] === 'low') $pclass = 'badge bg-info';
                                                        ?>
                                                        <span class="<?php echo $pclass; ?>"><?php echo ucfirst($row['priority']); ?></span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($row['requester_name'] ?? $row['requested_by']); ?></td>
                                                    <td><?php echo $row['created_at']; ?></td>
                                                    <td>
                                                        <?php
                                                            $status = $row['status'];
                                                            $sc = 'badge bg-light text-dark';
                                                            if ($status === 'pending') $sc = 'badge bg-warning text-dark';
                                                            if ($status === 'approved') $sc = 'badge bg-success';
                                                            if ($status === 'rejected') $sc = 'badge bg-danger';
                                                            if ($status === 'completed') $sc = 'badge bg-primary';
                                                        ?>
                                                        <span class="<?php echo $sc; ?>"><?php echo ucfirst($status); ?></span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($row['approver_name'] ?? ''); ?></td>
                                                    <td><?php echo $row['approved_at'] ?? ''; ?></td>
                                                    <td class="text-center">
                                                        <div class="btn-group" role="group">
                                                            <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#viewRequestModal"
                                                                    data-req='<?php echo htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); ?>'>
                                                                <i class="mdi mdi-eye-outline"></i>
                                                            </button>

                                                            <?php if (($user_role === 'admin' || $user_role === 'super_admin') && $row['status'] === 'pending'): ?>
                                                                <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#approveModal"
                                                                        data-id="<?php echo $row['id']; ?>"
                                                                        data-requestid="<?php echo htmlspecialchars($row['request_id']); ?>"
                                                                        data-product="<?php echo htmlspecialchars($row['product_name'] ?? ''); ?>"
                                                                        data-qty="<?php echo intval($row['requested_qty']); ?>">
                                                                    <i class="mdi mdi-check"></i>
                                                                </button>
                                                                <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal"
                                                                        data-id="<?php echo $row['id']; ?>"
                                                                        data-requestid="<?php echo htmlspecialchars($row['request_id']); ?>">
                                                                    <i class="mdi mdi-close"></i>
                                                                </button>
                                                            <?php endif; ?>

                                                            <?php if (($user_role === 'admin' || $user_role === 'super_admin' || intval($row['requested_by']) === intval($user_id)) && ($row['status'] === 'approved' || $row['status'] === 'pending')): ?>
                                                                <form method="post" style="display:inline-block;">
                                                                    <input type="hidden" name="sr_id" value="<?php echo $row['id']; ?>">
                                                                    <input type="hidden" name="action" value="complete">
                                                                    <button type="submit" class="btn btn-sm btn-primary" title="Mark Completed">
                                                                        <i class="mdi mdi-flag-checkered"></i>
                                                                    </button>
                                                                </form>
                                                            <?php endif; ?>

                                                            <?php if ($user_role === 'super_admin'): ?>
                                                                <!-- delete -->
                                                                <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal"
                                                                        data-id="<?php echo $row['id']; ?>"
                                                                        data-requestid="<?php echo htmlspecialchars($row['request_id']); ?>">
                                                                    <i class="mdi mdi-trash-can-outline"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="11" class="text-center py-4">
                                                    <div class="text-muted">
                                                        <i class="mdi mdi-clock-alert display-4"></i>
                                                        <h5 class="mt-2">No stock requests found.</h5>
                                                        <p>When linemen request stock, it will appear here.</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div> <!-- container -->
            </div> <!-- page-content -->

            <?php include('includes/footer.php'); ?>
        </div> <!-- main-content -->
    </div> <!-- layout-wrapper -->

    <!-- View Request Modal -->
    <div class="modal fade" id="viewRequestModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Request Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <dl class="row">
                        <dt class="col-sm-4">Request ID</dt><dd class="col-sm-8" id="v_request_id"></dd>
                        <dt class="col-sm-4">Product</dt><dd class="col-sm-8" id="v_product"></dd>
                        <dt class="col-sm-4">Requested Qty</dt><dd class="col-sm-8" id="v_requested_qty"></dd>
                        <dt class="col-sm-4">Current Qty</dt><dd class="col-sm-8" id="v_current_qty"></dd>
                        <dt class="col-sm-4">Priority</dt><dd class="col-sm-8" id="v_priority"></dd>
                        <dt class="col-sm-4">Requested By</dt><dd class="col-sm-8" id="v_requested_by"></dd>
                        <dt class="col-sm-4">Requested At</dt><dd class="col-sm-8" id="v_requested_at"></dd>
                        <dt class="col-sm-4">Status</dt><dd class="col-sm-8" id="v_status"></dd>
                        <dt class="col-sm-4">Approved By</dt><dd class="col-sm-8" id="v_approved_by"></dd>
                        <dt class="col-sm-4">Approved At</dt><dd class="col-sm-8" id="v_approved_at"></dd>
                        <dt class="col-sm-4">Notes</dt><dd class="col-sm-8" id="v_notes"></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <!-- Approve Modal -->
    <div class="modal fade" id="approveModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="sr_id" id="approve_sr_id">
                    <input type="hidden" name="action" value="approve">
                    <div class="modal-header">
                        <h5 class="modal-title">Approve Stock Request</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Approve request <strong id="approve_request_id"></strong> for <strong id="approve_product"></strong> (<span id="approve_qty"></span> units)?</p>
                        <div class="mb-3">
                            <label for="admin_note" class="form-label">Admin Note (optional)</label>
                            <textarea class="form-control" name="admin_note" id="admin_note" rows="2" placeholder="Add a note for record..."></textarea>
                        </div>
                        <div class="form-text">Approving will increase product stock by requested quantity and create a stock transaction record.</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Approve Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reject Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="sr_id" id="reject_sr_id">
                    <input type="hidden" name="action" value="reject">
                    <div class="modal-header">
                        <h5 class="modal-title">Reject Stock Request</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to reject request <strong id="reject_request_id"></strong>?</p>
                        <div class="mb-3">
                            <label for="reject_note" class="form-label">Reason / Note</label>
                            <textarea class="form-control" name="admin_note" id="reject_note" rows="2" placeholder="Enter reason for rejection (optional)"></textarea>
                        </div>
                        <div class="form-text">The requester will see this status and note.</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Reject Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Modal (super_admin only) -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="sr_id" id="delete_sr_id">
                    <input type="hidden" name="action" value="delete">
                    <div class="modal-header">
                        <h5 class="modal-title">Delete Stock Request</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Delete request <strong id="delete_request_id"></strong>? This action cannot be undone.</p>
                        <div class="form-text text-danger">Only super admin can delete requests.</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-outline-danger">Delete</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include('includes/rightbar.php'); ?>
    <?php include('includes/scripts.php'); ?>

    <script>
    // Populate view modal
    var viewModal = document.getElementById('viewRequestModal');
    viewModal && viewModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        var reqJson = button.getAttribute('data-req');
        try {
            var r = JSON.parse(reqJson);
            document.getElementById('v_request_id').textContent = r.request_id || '';
            document.getElementById('v_product').textContent = (r.product_name || '') + ' (' + (r.product_code || '') + ')';
            document.getElementById('v_requested_qty').textContent = r.requested_qty || '';
            document.getElementById('v_current_qty').textContent = r.current_qty || '';
            document.getElementById('v_priority').textContent = r.priority || '';
            document.getElementById('v_requested_by').textContent = r.requester_name || r.requested_by || '';
            document.getElementById('v_requested_at').textContent = r.created_at || '';
            document.getElementById('v_status').textContent = r.status || '';
            document.getElementById('v_approved_by').textContent = r.approver_name || '';
            document.getElementById('v_approved_at').textContent = r.approved_at || '';
            document.getElementById('v_notes').textContent = r.notes || '';
        } catch(e) {
            console.error('Invalid request JSON', e);
        }
    });

    // Approve modal populate
    var approveModal = document.getElementById('approveModal');
    approveModal && approveModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        document.getElementById('approve_sr_id').value = button.getAttribute('data-id') || '';
        document.getElementById('approve_request_id').textContent = button.getAttribute('data-requestid') || '';
        document.getElementById('approve_product').textContent = button.getAttribute('data-product') || '';
        document.getElementById('approve_qty').textContent = button.getAttribute('data-qty') || '';
        document.getElementById('admin_note').value = '';
    });

    // Reject modal populate
    var rejectModal = document.getElementById('rejectModal');
    rejectModal && rejectModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        document.getElementById('reject_sr_id').value = button.getAttribute('data-id') || '';
        document.getElementById('reject_request_id').textContent = button.getAttribute('data-requestid') || '';
        document.getElementById('reject_note').value = '';
    });

    // Delete modal populate
    var deleteModal = document.getElementById('deleteModal');
    deleteModal && deleteModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        document.getElementById('delete_sr_id').value = button.getAttribute('data-id') || '';
        document.getElementById('delete_request_id').textContent = button.getAttribute('data-requestid') || '';
    });
    </script>

</body>
</html>

<?php
// close connection
if (isset($conn)) mysqli_close($conn);
?>

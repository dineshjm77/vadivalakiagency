<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.getElementById('linemanSidebarToggle');
    const sidebar = document.querySelector('.vertical-menu');
    if (toggle && sidebar) {
        toggle.addEventListener('click', function () {
            sidebar.classList.toggle('show');
        });
    }

    document.querySelectorAll('.collect-btn').forEach(function(btn){
        btn.addEventListener('click', function(){
            document.getElementById('modal_order_id').value = this.dataset.orderId || '';
            document.getElementById('modal_customer_id').value = this.dataset.customerId || '';
            document.getElementById('modal_invoice').value = this.dataset.orderNumber || '';
            document.getElementById('modal_customer').value = this.dataset.customer || '';
            document.getElementById('modal_pending').value = '₹' + (this.dataset.pending || '0.00');
            const amt = document.getElementById('modal_amount_paid');
            if (amt) {
                amt.value = this.dataset.pending || '';
                amt.max = this.dataset.pending || '';
            }
        });
    });
});
</script>

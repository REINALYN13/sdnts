</div><!-- end container-fluid -->

<footer class="footer bg-light border-top mt-5 py-3">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center">
      <span class="text-muted small">&copy; <?= date('Y') ?> Sto. Domingo National Trade School &mdash; Academic System</span>
      <span class="text-muted small">Baloc, Sto. Domingo, Nueva Ecija</span>
    </div>
  </div>
</footer>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/js/jquery.dataTables.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function(){
  // Init all datatables
  if($('.datatable').length){ $('.datatable').DataTable({responsive:true, pageLength:25}); }
  // Auto-dismiss alerts after 5s
  setTimeout(function(){ $('.alert').fadeOut(); }, 5000);
  // Confirm delete
  $(document).on('click','.btn-delete',function(e){
    if(!confirm('Are you sure you want to delete this record? This action cannot be undone.')){ e.preventDefault(); }
  });
});
</script>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
</body>
</html>

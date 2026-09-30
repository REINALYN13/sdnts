/**
 * SDNTS - Main JavaScript
 * Santo Domingo National Trade School
 */

$(document).ready(function () {

  // ── DataTables init
  if ($('.datatable').length) {
    $('.datatable').DataTable({
      responsive: true,
      pageLength: 25,
      language: { search: "Search:", lengthMenu: "Show _MENU_ entries" }
    });
  }

  // ── Auto-dismiss alerts
  setTimeout(function () { $('.alert').fadeOut('slow'); }, 5000);

  // ── Confirm delete
  $(document).on('click', '.btn-delete', function (e) {
    if (!confirm('Delete this record? This cannot be undone.')) e.preventDefault();
  });

  // ── Grade input live highlight (range read from the input's own
  //    min/max attributes, which the server renders from the configured
  //    grade_min/grade_max system setting — frontend convenience only,
  //    the real enforcement happens server-side in admin/grades.php)
  $(document).on('input', '.grade-input', function () {
    var v = parseFloat($(this).val());
    var min = parseFloat($(this).attr('min'));
    var max = parseFloat($(this).attr('max'));
    if (isNaN(min)) min = 60;
    if (isNaN(max)) max = 100;
    $(this).removeClass('is-valid is-invalid');
    if (!isNaN(v)) {
      if (v >= min && v <= max) $(this).addClass('is-valid');
      else $(this).addClass('is-invalid');
    }
  });

  // ── Strand filter by program
  $('#s_progID, #enrollProgID').on('change', function () {
    var progID = $(this).val();
    var $strand = $(this).attr('id') === 's_progID' ? $('#s_strandID') : $('#enrollStrandID');
    $strand.find('option').each(function () {
      var opt = $(this);
      if (!opt.val() || opt.data('prog') == progID || !progID) opt.show();
      else opt.hide();
    });
    $strand.val('');
  });

  // ── Section filter by year (enrollment)
  $('#enrollYearID').on('change', function () {
    var yearID = $(this).val();
    $('#enrollSectionID option').each(function () {
      var opt = $(this);
      if (!opt.val() || opt.data('year') == yearID || !yearID) opt.show();
      else opt.hide();
    });
    $('#enrollSectionID').val('');
  });

  // ── Tooltip init
  $('[data-bs-toggle="tooltip"]').tooltip();

  // ── Sidebar active link highlight
  var current = window.location.pathname;
  $('.navbar-nav .nav-link').each(function () {
    if ($(this).attr('href') && current.indexOf($(this).attr('href')) !== -1) {
      $(this).addClass('active');
    }
  });

});

// ── Grade average preview (client-side)
function previewAverage(row) {
  var vals = [];
  $(row).find('.grade-input').each(function () {
    var v = parseFloat($(this).val());
    if (!isNaN(v)) vals.push(v);
  });
  var avg = vals.length ? (vals.reduce((a, b) => a + b, 0) / vals.length).toFixed(2) : '-';
  $(row).find('.avg-preview').text(avg);
}

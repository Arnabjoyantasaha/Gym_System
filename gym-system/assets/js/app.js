/* assets/js/app.js — FitCore Pro minimal UI scripts */

'use strict';

// ── Auto-dismiss alerts ──
document.querySelectorAll('.alert[data-dismiss]').forEach(el => {
  setTimeout(() => {
    el.style.transition = 'opacity .5s';
    el.style.opacity = '0';
    setTimeout(() => el.remove(), 500);
  }, parseInt(el.dataset.dismiss, 10) || 4000);
});

// ── Confirm dangerous actions ──
document.querySelectorAll('[data-confirm]').forEach(el => {
  el.addEventListener('click', function(e) {
    if (!confirm(this.dataset.confirm || 'Are you sure?')) {
      e.preventDefault();
    }
  });
});

// ── BMI Calculator (member/bmi.php) ──
const bmiForm = document.getElementById('bmiCalcForm');
if (bmiForm) {
  function updateBMI() {
    const h = parseFloat(document.getElementById('height').value);
    const w = parseFloat(document.getElementById('weight').value);
    const out = document.getElementById('bmiResult');
    if (!h || !w || h <= 0 || w <= 0) { out.textContent = '—'; return; }
    const bmi = w / Math.pow(h / 100, 2);
    const val = bmi.toFixed(2);
    let cat = 'Underweight', color = '#64b5f6';
    if (bmi >= 18.5) { cat='Normal';     color='#4CAF50'; }
    if (bmi >= 25.0) { cat='Overweight'; color='#ffa726'; }
    if (bmi >= 30.0) { cat='Obese';      color='#ef5350'; }
    out.innerHTML = `<span style="font-size:2rem;font-family:'Bebas Neue',sans-serif;color:${color}">${val}</span>
                     <span style="font-size:12px;letter-spacing:1px;color:${color};display:block">${cat}</span>`;
    // hidden field
    const hidden = document.getElementById('bmi_value');
    if (hidden) hidden.value = val;
    // update bar
    const bar = document.getElementById('bmiFill');
    if (bar) {
      const pct = Math.min(100, Math.max(0, ((bmi - 10) / 30) * 100));
      bar.style.width = pct + '%';
    }
  }
  document.getElementById('height')?.addEventListener('input', updateBMI);
  document.getElementById('weight')?.addEventListener('input', updateBMI);
}

// ── Table live search ──
const searchInput = document.getElementById('tableSearch');
if (searchInput) {
  searchInput.addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('tbody tr').forEach(tr => {
      tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  });
}

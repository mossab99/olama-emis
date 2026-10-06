(() => {
  'use strict';
  const config = window.OlamaEmisStaff;
  async function request(action, params, signal) {
    const url = new URL(config.url, window.location.href);
    url.search = new URLSearchParams({ action, nonce: config.nonce, ...params }).toString();
    const response = await fetch(url, { credentials: 'same-origin', signal });
    if (!response.ok) throw new Error('تعذر الاتصال بالسجل.');
    return response.json();
  }
  document.querySelectorAll('.olama-emis__lookup').forEach(box => {
    const input = box.querySelector('input'), results = box.querySelector('[role=status]');
    let timer, controller, sequence = 0;
    input.addEventListener('input', () => {
      clearTimeout(timer); controller?.abort(); const current = ++sequence; results.replaceChildren();
      if (input.value.trim().length < 2 || !config) return;
      timer = setTimeout(async () => {
        controller = new AbortController(); results.textContent = 'جارٍ البحث…';
        try {
          const result = await request('olama_emis_staff_lookup', { q: input.value.trim() }, controller.signal);
          if (current !== sequence) return;
          results.replaceChildren();
          if (!result.success) throw new Error('تعذر البحث.');
          if (!result.data.length) results.textContent = 'لا توجد نتائج.';
          result.data.forEach(staff => {
            const a = document.createElement('a'); a.className = 'button';
            const url = new URL(window.location.href); const context = JSON.parse(box.dataset.context);
            Object.entries(context).forEach(([key, value]) => url.searchParams.set(key, value));
            url.searchParams.set('staff_id', staff.id); url.hash = 'olama-emis-staff-editor'; a.href = url.href;
            a.textContent = `${staff.first_name} ${staff.family_name} · ${staff.identity_number}`;
            results.append(a);
          });
        } catch (error) { if (error.name !== 'AbortError' && current === sequence) results.textContent = error.message; }
      }, 300);
    });
  });
  document.querySelectorAll('input[name=identity_number]').forEach(input => {
    const status = input.parentElement.querySelector('[role=status]'); let timer, controller, sequence = 0;
    if (!config || !status || input.closest('fieldset')?.disabled) return;
    input.addEventListener('input', () => {
      clearTimeout(timer); controller?.abort(); const current = ++sequence; status.textContent = '';
      timer = setTimeout(async () => {
        if (!input.value.trim()) return;
        controller = new AbortController(); status.textContent = 'جارٍ فحص الرقم…';
        try {
          const result = await request('olama_emis_staff_identity', { number: input.value.trim() }, controller.signal);
          if (current !== sequence) return;
          const same = Number(input.form.querySelector('[name=staff_id]').value) === Number(result.data.staff_id);
          status.textContent = result.success && same && result.data.staff_id ? 'الرقم يخص الموظف الحالي.' : result.data.message;
        } catch (error) { if (error.name !== 'AbortError' && current === sequence) status.textContent = error.message; }
      }, 300);
    });
  });
  document.querySelectorAll('.olama-emis__workload-grid').forEach(grid => {
    const form = grid.closest('form'), tbody = grid.tBodies[0], template = form.querySelector('template');
    const error = form.querySelector('[role=alert]'), total = form.querySelector('.olama-emis__workload-total');
    function update() {
      error.textContent = '';
      total.textContent = [...tbody.querySelectorAll('[data-key=weekly_periods]')].reduce((sum, field) => sum + (Number(field.value) || 0), 0);
    }
    form.querySelector('.olama-emis__add-workload')?.addEventListener('click', () => {
      if (tbody.rows.length >= 100) { error.textContent = 'الحد الأقصى 100 توزيع.'; return; }
      tbody.append(template.content.cloneNode(true)); error.textContent = ''; update();
    });
    grid.addEventListener('click', event => {
      if (event.target.closest('.olama-emis__remove-workload')) { event.target.closest('tr').remove(); update(); }
    });
    grid.addEventListener('input', update);
    form.addEventListener('submit', event => {
      const rows = [...tbody.rows].map(row => Object.fromEntries([...row.querySelectorAll('[data-key]')].map(field => [field.dataset.key, field.value.trim()])));
      const seen = new Set(); let message = '';
      for (const row of rows) {
        if (Object.values(row).every(value => value === '')) continue;
        if (!row.subject || !/^\d+$/.test(row.weekly_periods)) { message = 'المبحث وعدد الحصص الصحيح مطلوبان لكل توزيع.'; break; }
        const key = [row.subject, row.grade, row.section].join('|').toLocaleLowerCase();
        if (seen.has(key)) { message = 'توزيع مكرر لنفس المبحث والصف والشعبة.'; break; } seen.add(key);
      }
      if (Number(total.textContent) > 10000) message = 'مجموع الحصص يتجاوز 10000.';
      error.textContent = message;
      if (message) { event.preventDefault(); error.scrollIntoView({ block: 'center' }); return; }
      form.querySelector('[name=workloads_json]').value = JSON.stringify(rows);
    });
    update();
  });
})();

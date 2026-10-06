(function () {
    'use strict';
    document.querySelectorAll('.olama-emis__room-form').forEach(function (form) {
        var body = form.querySelector('tbody');
        var error = form.querySelector('.olama-emis__grid-error');
        var stored = new Map();
        JSON.parse(form.dataset.existingRooms || '[]').forEach(function (room) {
            stored.set(room.room_number.trim().toLocaleLowerCase(),Number(room.room_id));
        });
        function report(message) { error.textContent = message; }
        form.querySelector('.olama-emis__add-room')?.addEventListener('click', function () {
            if (body.rows.length >= 100) { report('الحد الأقصى 100 غرفة في الدفعة.'); return; }
            var template = form.querySelector('template').content.cloneNode(true);
            body.appendChild(template);
            body.lastElementChild.querySelector('input').focus();
        });
        form.addEventListener('click', function (event) {
            var row = event.target.closest('tr');
            if (!row) return;
            if (event.target.matches('.olama-emis__remove-room')) row.remove();
            if (event.target.matches('.olama-emis__calculate-area')) {
                var length = row.querySelector('[data-key="length"]').value;
                var width = row.querySelector('[data-key="width"]').value;
                if (length !== '' && width !== '' && Number(length) >= 0 && Number(width) >= 0) {
                    row.querySelector('[data-key="area"]').value = (Number(length) * Number(width)).toFixed(4);
                    report('');
                } else report('أدخل طولاً وعرضاً صالحين لاقتراح المساحة.');
            }
        });
        form.addEventListener('input', function () {
            var numbers = new Set(); var duplicate = false;
            body.querySelectorAll('[data-key="room_number"]').forEach(function (input) {
                var number = input.value.trim().toLocaleLowerCase();
                var id = Number(input.closest('tr').dataset.roomId);
                if (number && (numbers.has(number) || (stored.has(number) && stored.get(number) !== id))) duplicate = true;
                if (number) numbers.add(number);
            });
            report(duplicate ? 'رقم غرفة مكرر في سجل هذا البناء والعام، بما فيه الغرف الأخرى والمؤرشفة.' : '');
        });
        form.addEventListener('submit', function (event) {
            var rows = []; var numbers = new Set(); var invalid = '';
            body.querySelectorAll('tr').forEach(function (row) {
                var value = {room_id: Number(row.dataset.roomId)};
                row.querySelectorAll('[data-key]').forEach(function (input) {
                    value[input.dataset.key] = input.type === 'checkbox' ? (input.checked ? 1 : 0) : input.value;
                });
                var number = value.room_number.trim().toLocaleLowerCase();
                if (!number || !value.floor_id) invalid = 'أدخل رقم الغرفة واختر الطابق لكل صف.';
                if (numbers.has(number)) invalid = 'يوجد رقم غرفة مكرر في الدفعة.';
                if (stored.has(number) && stored.get(number) !== value.room_id) invalid = 'رقم الغرفة مسجل لهذا البناء والعام. افتح سجله الحالي.';
                numbers.add(number); rows.push(value);
            });
            if (invalid) { event.preventDefault(); report(invalid); return; }
            form.querySelector('[name="rooms_json"]').value = JSON.stringify(rows);
        });
    });
}());

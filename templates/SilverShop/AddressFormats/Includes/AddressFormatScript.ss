<style>
.addressformat-hidden,
.field.addressformat-hidden { display: none !important; }
</style>
<script type="application/json" class="addressformat-rules">$RulesJson.RAW</script>
<script>
(function () {
    if (window.__addressFormatInit) { return; }
    window.__addressFormatInit = true;

    function holder(el) { return el.closest('.field') || el.parentElement; }

    function apply(form, rules) {
        var countryField = form.querySelector('[name="Country"]');
        var code = countryField ? String(countryField.value || '').toUpperCase() : '';
        var rule = rules[code] || rules['default'] || { hidden: [], required: [], labels: {}, postcodePattern: '' };
        var hidden = rule.hidden || [];
        var required = rule.required || [];
        var labels = rule.labels || {};

        form.querySelectorAll('[data-addressformat-field]').forEach(function (input) {
            var name = input.getAttribute('data-addressformat-field');
            var box = holder(input);
            var isHidden = hidden.indexOf(name) !== -1;

            if (box) { box.classList.toggle('addressformat-hidden', isHidden); }

            if (labels[name] && box) {
                var lbl = box.querySelector('label');
                if (lbl) { lbl.textContent = labels[name]; }
            }

            var req = !isHidden && required.indexOf(name) !== -1;
            input.required = req;
            if (req) { input.setAttribute('aria-required', 'true'); } else { input.removeAttribute('aria-required'); }
            if (box) { box.classList.toggle('required', req); }

            if (name === 'PostalCode') {
                if (rule.postcodePattern) { input.setAttribute('pattern', rule.postcodePattern); }
                else { input.removeAttribute('pattern'); }
            }
        });

        form.dispatchEvent(new CustomEvent('addressformat:changed', { detail: { country: code } }));
    }

    function wire() {
        document.querySelectorAll('script.addressformat-rules').forEach(function (s) {
            var form = s.closest('form');
            if (!form || form.__afWired) { return; }
            form.__afWired = true;

            var rules;
            try { rules = JSON.parse(s.textContent); } catch (e) { return; }

            var countryField = form.querySelector('[name="Country"]');
            if (countryField) {
                countryField.addEventListener('change', function () { apply(form, rules); });
            }
            apply(form, rules);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', wire);
    } else {
        wire();
    }
})();
</script>

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

    // The country <select> within a scope. On the single-page checkout the address fields are
    // component-namespaced (e.g. "…ShippingAddress_Country"), so match a plain "Country" OR any
    // "<ns>_Country"; on the account address book the names are plain.
    function countryIn(scope) {
        return scope.querySelector('[name="Country"], [name$="_Country"]');
    }

    function apply(scope, rules) {
        var countryField = countryIn(scope);
        var code = countryField ? String(countryField.value || '').toUpperCase() : '';
        var rule = rules[code] || rules['default'] || { hidden: [], required: [], labels: {}, postcodePattern: '' };
        var hidden = rule.hidden || [];
        var required = rule.required || [];
        var labels = rule.labels || {};

        scope.querySelectorAll('[data-addressformat-field]').forEach(function (input) {
            var name = input.getAttribute('data-addressformat-field');
            var box = holder(input);
            var isHidden = hidden.indexOf(name) !== -1;

            // SilverStripe renders the extra class on BOTH the input (which carries
            // data-addressformat-field) and its .field holder, and the CSS hides either — so toggle both.
            input.classList.toggle('addressformat-hidden', isHidden);
            if (box && box !== input) { box.classList.toggle('addressformat-hidden', isHidden); }

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

        scope.dispatchEvent(new CustomEvent('addressformat:changed', { detail: { country: code }, bubbles: true }));
    }

    function wire() {
        document.querySelectorAll('script.addressformat-rules').forEach(function (s) {
            // Scope to the address block: the checkout wraps each address in .silvershop-address-fields
            // (shipping AND billing, each with its own namespaced Country), so wire per block; fall back
            // to the whole form for the account address book (a single, plain-named address).
            var scope = s.closest('.silvershop-address-fields') || s.closest('form');
            if (!scope || scope.__afWired) { return; }
            scope.__afWired = true;

            var rules;
            try { rules = JSON.parse(s.textContent); } catch (e) { return; }

            var countryField = countryIn(scope);
            if (countryField) {
                countryField.addEventListener('change', function () { apply(scope, rules); });
            }
            apply(scope, rules);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', wire);
    } else {
        wire();
    }
})();
</script>

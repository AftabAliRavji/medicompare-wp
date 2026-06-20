(function finalSmartBulletRepair() {
    console.log("Running FINAL smart bullet repair…");

    const clean = str =>
        str
            .toLowerCase()
            .replace(/low priority|high priority|medium priority/gi, "")
            .replace(/→/g, "")
            .replace(/\s+/g, " ")
            .trim();

    const h3s = document.querySelectorAll("#requirements h3");

    h3s.forEach(h3 => {
        const subsectionTitle = clean(
            h3.textContent.replace(/^\d+(\.\d+)*/, "")
        );

        const ul = h3.nextElementSibling;
        if (!ul || ul.tagName !== "UL") return;

        const lis = [...ul.querySelectorAll("li")];

        // Normalise all bullet titles
        const normalised = lis.map(li => clean(li.textContent));

        // Remove duplicates (keep first)
        const seen = new Set();
        lis.forEach(li => {
            const raw = clean(li.textContent);
            if (seen.has(raw)) {
                console.log("Removing duplicate:", raw);
                li.remove();
            } else {
                seen.add(raw);
            }
        });

        // Check if a bullet matches the subsection title
        const hasMatch = [...ul.querySelectorAll("li")].some(li => {
            return clean(li.textContent) === subsectionTitle;
        });

        // If missing → create bullet
        if (!hasMatch) {
            console.log("Adding missing bullet for:", subsectionTitle);

            const li = document.createElement("li");
            li.dataset.cardTitle = subsectionTitle;
            li.innerHTML = `<strong>${subsectionTitle}</strong> → `;
            ul.insertBefore(li, ul.firstChild);
        }

        // Ensure all bullets have correct data-card-title
        ul.querySelectorAll("li").forEach(li => {
            li.dataset.cardTitle = clean(li.textContent);
        });
    });

    if (typeof saveStateToDB === "function") {
        saveStateToDB();
        console.log("FINAL smart repair complete and saved.");
    } else {
        console.warn("saveStateToDB() not found — repaired but not saved.");
    }
})();



then run this -
(function tidyRequirementsOnce() {
    console.log("Running minimal, safe tidy…");

    // 1) Clean duplicated priority text in <h3>, keep only the coloured span
    document.querySelectorAll("#requirements h3").forEach(h3 => {
        // Example: "9.1 Supplier section HIGH PRIORITY <span>HIGH PRIORITY</span>"
        // We remove the plain "HIGH PRIORITY" / "LOW PRIORITY" / "MED/HIGH PRIORITY"
        h3.innerHTML = h3.innerHTML
            .replace(/\s*(LOW PRIORITY|HIGH PRIORITY|MED\/HIGH PRIORITY)\s*/gi, " ")
            .replace(/\s+/g, " ")
            .trim();
    });

    // 2) Normalise all <li> bullets: keep text, fix data-card-title, keep your structure
    document.querySelectorAll("#requirements ul li").forEach(li => {
        // Get visible text before the arrow
        const fullText = li.textContent.replace(/→.*/, "").trim();

        if (!fullText) return;

        // Your convention: first word (lowercased) as data-card-title
        const firstWord = fullText.split(/\s+/)[0].toLowerCase();

        li.dataset.cardTitle = firstWord;
        li.innerHTML = `<strong>${fullText}</strong> → `;
    });

    if (typeof saveStateToDB === "function") {
        saveStateToDB();
        console.log("Tidy complete and saved.");
    } else {
        console.warn("saveStateToDB() not found — changes not persisted.");
    }
})();

(function repairBoard() {
    console.log("Starting full board repair…");

    const reqContainer = document.getElementById("requirements");
    if (!reqContainer) {
        console.error("Requirements container not found.");
        return;
    }

    /* -----------------------------------------
       STEP 1 — FIX SUBSECTION NUMBERING + IDs
    ----------------------------------------- */
    const h2s = [...reqContainer.querySelectorAll("h2")];

    h2s.forEach((h2, mainIndex) => {
        const mainNumber = mainIndex + 1;
        const mainTitle = h2.textContent.replace(/^\d+\.\s*/, "").trim();
        h2.textContent = `${mainNumber}. ${mainTitle}`;

        let subCounter = 1;
        let node = h2.nextElementSibling;

        while (node && node.tagName !== "H2") {
            if (node.tagName === "H3") {
                const cleanTitle = node.textContent.replace(/^\d+(\.\d+)*\s*/, "").trim();
                const newId = `req-${mainNumber}-${subCounter}`;
                node.id = newId;
                node.innerHTML = `${mainNumber}.${subCounter} ${cleanTitle}${node.querySelector("span") ? " " + node.querySelector("span").outerHTML : ""}`;
                subCounter++;
            }
            node = node.nextElementSibling;
        }
    });

    /* -----------------------------------------
       STEP 2 — FIX ALL <li> data-card-title
    ----------------------------------------- */
    reqContainer.querySelectorAll("ul li").forEach(li => {
        const strong = li.querySelector("strong");
        if (strong) {
            li.dataset.cardTitle = strong.textContent.trim();
        } else {
            // fallback: use text before arrow
            const text = li.textContent.split("→")[0].trim();
            li.dataset.cardTitle = text;
        }
    });

    /* -----------------------------------------
       STEP 3 — MOVE ORPHANED WORK DONE BOXES
    ----------------------------------------- */
    document.querySelectorAll(".completed-box").forEach(box => {
        const parentH3 = box.closest("h3") || box.previousElementSibling;
        if (!parentH3) return;

        const ul = parentH3.nextElementSibling;
        if (!ul || ul.tagName !== "UL") return;

        const cardTitle = parentH3.textContent.replace(/^\d+(\.\d+)*\s*/, "").trim();

        const targetLi = [...ul.querySelectorAll("li")].find(li =>
            li.dataset.cardTitle &&
            li.dataset.cardTitle.toLowerCase() === cardTitle.toLowerCase()
        );

        if (targetLi) {
            box.remove();
            targetLi.insertAdjacentElement("afterend", box);
        }
    });

    /* -----------------------------------------
       STEP 4 — SAVE CLEANED STATE TO DB
    ----------------------------------------- */
    if (typeof saveStateToDB === "function") {
        saveStateToDB();
        console.log("Board repaired and saved.");
    } else {
        console.warn("saveStateToDB() not found — board repaired but not saved.");
    }

})();

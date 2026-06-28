(function repairCardTargets() {
    console.log("Repairing Kanban card targets…");

    document.querySelectorAll(".kanban-item").forEach(card => {
        const oldTarget = card.dataset.target;
        if (!oldTarget) return;

        const oldId = oldTarget.replace("#", "");
        const h3s = [...document.querySelectorAll("#requirements h3")];

        // Find the closest matching subsection by title
        const cardTitle = card.childNodes[0].textContent.trim().toLowerCase();

        let bestMatch = null;

        h3s.forEach(h3 => {
            const cleanTitle = h3.textContent.replace(/^\d+(\.\d+)*/, "").trim().toLowerCase();
            if (cleanTitle.includes(cardTitle) || cardTitle.includes(cleanTitle)) {
                bestMatch = h3;
            }
        });

        // Fallback: match by prefix number
        if (!bestMatch) {
            const prefix = oldId.split("-").slice(0, 2).join("-");
            bestMatch = h3s.find(h3 => h3.id.startsWith(prefix));
        }

        if (bestMatch) {
            card.dataset.target = `#${bestMatch.id}`;
            console.log(`Updated card "${cardTitle}" → ${bestMatch.id}`);
        } else {
            console.warn(`No match found for card "${cardTitle}"`);
        }
    });

    if (typeof saveStateToDB === "function") {
        saveStateToDB();
        console.log("Card targets repaired and saved.");
    }
})();

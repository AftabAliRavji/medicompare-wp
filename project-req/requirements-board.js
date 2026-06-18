/* -----------------------------------------
   CONFIG
----------------------------------------- */

const ajaxurl = "/wp-admin/admin-ajax.php";

/* -----------------------------------------
   ON LOAD
----------------------------------------- */

document.addEventListener("DOMContentLoaded", () => {
    restoreStateFromDB();
});

/* -----------------------------------------
   SAVE TO DATABASE
----------------------------------------- */

function saveStateToDB() {
    const board = {
        kanbanHTML: document.querySelector(".kanban-wrapper").innerHTML,
        requirementsHTML: document.getElementById("requirements").innerHTML
    };

    jQuery.post(ajaxurl, {
        action: "save_requirements_board",
        board_json: JSON.stringify(board)
    });
}

/* -----------------------------------------
   LOAD FROM DATABASE
----------------------------------------- */

function restoreStateFromDB() {
    jQuery.post(ajaxurl, { action: "load_requirements_board" }, function(response) {
        if (response.success && response.data.board_json) {
            const board = JSON.parse(response.data.board_json);

            document.querySelector(".kanban-wrapper").innerHTML = board.kanbanHTML;
            document.getElementById("requirements").innerHTML = board.requirementsHTML;
        }

        // Reattach everything
        ensureDeleteButtonsOnAllCards();
        enableDragAndDrop();
        attachKanbanClickHandlers();
        populateMainSectionDropdown();
        applyPriorityColoursToExistingCards();
    });
}

/* -----------------------------------------
   DELETE BUTTONS
----------------------------------------- */

document.addEventListener("click", (e) => {
    if (!e.target.classList.contains("delete-btn")) return;

    e.stopPropagation();

    const card = e.target.closest(".kanban-item");
    const target = card.dataset.target;

    card.remove();

    if (target && target.startsWith("#req-")) {
        const h3 = document.querySelector(target);
        if (h3) {
            const ul = h3.nextElementSibling;
            h3.remove();
            if (ul && ul.tagName === "UL") ul.remove();
            cleanupEmptyMainSections();
        }
    }

    saveStateToDB();
});

/* -----------------------------------------
   MODAL
----------------------------------------- */

const modalBg = document.getElementById("modalBg");

document.getElementById("openModalBtn").onclick = () => {
    document.getElementById("taskTitle").value = "";
    document.getElementById("taskDetails").value = "";
    document.getElementById("newMainSectionInput").value = "";
    document.getElementById("addTypeSelect").value = "subsection";
    document.getElementById("prioritySelect").value = "low";

    populateMainSectionDropdown();
    modalBg.style.display = "flex";
};

document.getElementById("cancelModalBtn").onclick = () => {
    modalBg.style.display = "none";
};

/* -----------------------------------------
   MAIN SECTION DROPDOWN
----------------------------------------- */

function populateMainSectionDropdown() {
    const mainSelect = document.getElementById("mainSectionSelect");
    mainSelect.innerHTML = "";

    const newOpt = document.createElement("option");
    newOpt.value = "__new__";
    newOpt.textContent = "Not There (Create New Main Section)";
    mainSelect.appendChild(newOpt);

    const sections = document.querySelectorAll("#requirements h2");
    sections.forEach(sec => {
        const opt = document.createElement("option");
        opt.value = sec.textContent.trim();
        opt.textContent = sec.textContent.trim();
        mainSelect.appendChild(opt);
    });

    populateSubsectionDropdown();
}

/* -----------------------------------------
   SUBSECTION DROPDOWN
----------------------------------------- */

document.getElementById("addTypeSelect").addEventListener("change", populateSubsectionDropdown);
document.getElementById("mainSectionSelect").addEventListener("change", populateSubsectionDropdown);
document.getElementById("mainSectionSelect").addEventListener("change", () => {
    const sel = document.getElementById("mainSectionSelect").value;
    const input = document.getElementById("newMainSectionInput");
    input.style.display = sel === "__new__" ? "block" : "none";
});

function populateSubsectionDropdown() {
    const addType = document.getElementById("addTypeSelect").value;
    const mainSel = document.getElementById("mainSectionSelect").value;

    const subsectionLabel = document.getElementById("subsectionLabel");
    const subsectionSelect = document.getElementById("subsectionSelect");
    const newMainInput = document.getElementById("newMainSectionInput");

    if (mainSel === "__new__") {
        subsectionLabel.style.display = "none";
        subsectionSelect.style.display = "none";
        newMainInput.style.display = "block";
        return;
    }

    newMainInput.style.display = "none";

    if (addType === "bullet") {
        subsectionLabel.style.display = "block";
        subsectionSelect.style.display = "block";

        const subsections = findSubsections(mainSel);
        subsectionSelect.innerHTML = "";

        subsections.forEach(h3 => {
            const opt = document.createElement("option");
            opt.value = h3.id;
            opt.textContent = h3.textContent.replace(/<[^>]*>/g, "").trim();
            subsectionSelect.appendChild(opt);
        });

    } else {
        subsectionLabel.style.display = "none";
        subsectionSelect.style.display = "none";
    }
}

/* -----------------------------------------
   FIND SUBSECTIONS
----------------------------------------- */

function findSubsections(sectionTitle) {
    const h2s = [...document.querySelectorAll("#requirements h2")];
    let startIndex = h2s.findIndex(h => h.textContent.trim() === sectionTitle);
    if (startIndex === -1) return [];

    const startNode = h2s[startIndex];
    const nextH2 = h2s[startIndex + 1];

    let subsections = [];
    let node = startNode.nextElementSibling;

    while (node && node !== nextH2) {
        if (node.tagName === "H3") subsections.push(node);
        node = node.nextElementSibling;
    }

    return subsections;
}

/* -----------------------------------------
   ADD TASK
----------------------------------------- */

document.getElementById("addTaskBtn").onclick = () => {
    const mainSection = document.getElementById("mainSectionSelect").value;
    const addType = document.getElementById("addTypeSelect").value;
    const title = document.getElementById("taskTitle").value.trim();
    const details = document.getElementById("taskDetails").value.trim();

    if (!title) return alert("Please enter a title.");

    if (mainSection === "__new__") {
        const newTitle = document.getElementById("newMainSectionInput").value.trim();
        if (!newTitle) return alert("Please enter a main section title.");
        createNewMainSection(newTitle, title, details);

    } else if (addType === "subsection") {
        addNewSubsection(mainSection, title, details);

    } else {
        const subsectionId = document.getElementById("subsectionSelect").value;
        addBulletPoint(subsectionId, title, details);
    }

    modalBg.style.display = "none";
    saveStateToDB();
};

/* -----------------------------------------
   ADD NEW SUBSECTION
----------------------------------------- */

function addNewSubsection(mainSection, title, details) {
    const subsections = findSubsections(mainSection);

    let nextNumber = 1;
    if (subsections.length > 0) {
        const last = subsections[subsections.length - 1];
        const match = last.id.match(/req-(\d+)-(\d+)/);
        if (match) nextNumber = parseInt(match[2]) + 1;
    }

    const sectionNumber = findSectionNumber(mainSection);
    const newId = `req-${sectionNumber}-${nextNumber}`;
    const newHeading = `${sectionNumber}.${nextNumber} ${title}`;

    const h3 = document.createElement("h3");
    h3.id = newId;

    const priority = document.getElementById("prioritySelect").value;
    let prioritySpan = "";
    if (priority === "high") prioritySpan = ` <span class="priority-high">HIGH PRIORITY</span>`;
    if (priority === "med")  prioritySpan = ` <span class="priority-med">MED/HIGH PRIORITY</span>`;
    if (priority === "low")  prioritySpan = ` <span class="priority-low">LOW PRIORITY</span>`;

    h3.innerHTML = `${newHeading}${prioritySpan}`;

    const ul = document.createElement("ul");
    const li = document.createElement("li");
    li.innerHTML = `<strong>${title}</strong> → ${details}`;
    ul.appendChild(li);

    const lastSub = subsections[subsections.length - 1];
    if (lastSub) {
        lastSub.nextElementSibling.insertAdjacentElement("afterend", ul);
        ul.insertAdjacentElement("beforebegin", h3);
    } else {
        const h2s = [...document.querySelectorAll("#requirements h2")];
        const h2 = h2s.find(h => h.textContent.trim() === mainSection);
        h2.insertAdjacentElement("afterend", h3);
        h3.insertAdjacentElement("afterend", ul);
    }

    createKanbanCard(title, `#${newId}`);
}

/* -----------------------------------------
   FIND SECTION NUMBER
----------------------------------------- */

function findSectionNumber(sectionTitle) {
    const h2s = [...document.querySelectorAll("#requirements h2")];
    return h2s.findIndex(h => h.textContent.trim() === sectionTitle) + 1;
}

/* -----------------------------------------
   ADD BULLET POINT
----------------------------------------- */

function addBulletPoint(subsectionId, title, details) {
    const h3 = document.getElementById(subsectionId);
    const ul = h3.nextElementSibling;

    const li = document.createElement("li");
    li.innerHTML = `<strong>${title}</strong> → ${details}`;
    ul.appendChild(li);

    createKanbanCard(title, `#${subsectionId}`);
}

/* -----------------------------------------
   CREATE KANBAN CARD
----------------------------------------- */

function createKanbanCard(title, target) {
    const card = document.createElement("div");
    card.className = "kanban-item";
    card.draggable = true;
    card.dataset.target = target;
    card.textContent = title;

    const req = document.querySelector(target);
    if (req) {
        if (req.innerHTML.includes("priority-high")) card.classList.add("high");
        if (req.innerHTML.includes("priority-med")) card.classList.add("med");
        if (req.innerHTML.includes("priority-low")) card.classList.add("low");
    }

    const del = document.createElement("button");
    del.className = "delete-btn";
    del.textContent = "X";
    card.appendChild(del);

    document.getElementById("todo").appendChild(card);

    enableDragAndDrop();
    attachKanbanClickHandlers();
}

/* -----------------------------------------
   KANBAN CLICK → SCROLL + HIGHLIGHT
----------------------------------------- */

function attachKanbanClickHandlers() {
    document.querySelectorAll(".kanban-item").forEach(item => {
        item.onclick = (e) => {
            if (e.target.classList.contains("delete-btn")) return;

            const target = item.dataset.target;
            const el = document.querySelector(target);
            if (!el) return;

            el.classList.add("highlight");
            setTimeout(() => el.classList.remove("highlight"), 2000);

            el.scrollIntoView({ behavior: "smooth", block: "center" });
        };
    });
}

/* -----------------------------------------
   DRAG & DROP
----------------------------------------- */

function enableDragAndDrop() {
    const items = document.querySelectorAll(".kanban-item");
    const columns = document.querySelectorAll(".kanban-column");

    items.forEach(item => {
        item.addEventListener("dragstart", () => item.classList.add("dragging"));
        item.addEventListener("dragend", () => {
            item.classList.remove("dragging");
            saveStateToDB();
        });
    });

    columns.forEach(col => {
        col.addEventListener("dragover", e => {
            e.preventDefault();
            const dragging = document.querySelector(".dragging");
            col.appendChild(dragging);
        });
    });
}

/* -----------------------------------------
   PRIORITY COLOURS
----------------------------------------- */

function applyPriorityColoursToExistingCards() {
    document.querySelectorAll(".kanban-item").forEach(card => {
        const target = card.dataset.target;
        const req = document.querySelector(target);
        if (!req) return;

        if (req.innerHTML.includes("priority-high")) card.classList.add("high");
        if (req.innerHTML.includes("priority-med")) card.classList.add("med");
        if (req.innerHTML.includes("priority-low")) card.classList.add("low");
    });
}

/* -----------------------------------------
   CREATE NEW MAIN SECTION
----------------------------------------- */

function createNewMainSection(sectionTitle, firstSubTitle, details) {
    const h2s = [...document.querySelectorAll("#requirements h2")];
    const nextMainNumber = h2s.length + 1;

    const h2 = document.createElement("h2");
    h2.textContent = `${nextMainNumber}. ${sectionTitle}`;

    const newId = `req-${nextMainNumber}-1`;

    const priority = document.getElementById("prioritySelect").value;
    let prioritySpan = "";
    if (priority === "high") prioritySpan = ` <span class="priority-high">HIGH PRIORITY</span>`;
    if (priority === "med")  prioritySpan = ` <span class="priority-med">MED/HIGH PRIORITY</span>`;
    if (priority === "low")  prioritySpan = ` <span class="priority-low">LOW PRIORITY</span>`;

    const h3 = document.createElement("h3");
    h3.id = newId;
    h3.innerHTML = `${nextMainNumber}.1 ${firstSubTitle}${prioritySpan}`;

    const ul = document.createElement("ul");
    const li = document.createElement("li");
    li.innerHTML = `<strong>${firstSubTitle}</strong> → ${details}`;
    ul.appendChild(li);

    const req = document.getElementById("requirements");
    req.appendChild(document.createElement("div")).className = "section-divider";
    req.appendChild(h2);
    req.appendChild(h3);
    req.appendChild(ul);

    createKanbanCard(firstSubTitle, `#${newId}`);

    saveStateToDB();
    populateMainSectionDropdown();
}

/* -----------------------------------------
   CLEANUP EMPTY MAIN SECTIONS
----------------------------------------- */

function cleanupEmptyMainSections() {
    const h2s = document.querySelectorAll("#requirements h2");

    h2s.forEach(h2 => {
        let next = h2.nextElementSibling;

        if (!next || next.tagName !== "H3") {
            const prev = h2.previousElementSibling;
            if (prev && prev.classList.contains("section-divider")) {
                prev.remove();
            }

            h2.remove();
        }
    });
}

/* -----------------------------------------
   ENSURE DELETE BUTTONS
----------------------------------------- */

function ensureDeleteButtonsOnAllCards() {
    document.querySelectorAll(".kanban-item").forEach(card => {
        if (card.querySelector(".delete-btn")) return;

        const del = document.createElement("button");
        del.className = "delete-btn";
        del.textContent = "X";
        card.appendChild(del);
    });
}

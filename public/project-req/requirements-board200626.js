/* -----------------------------------------
   CONFIG
----------------------------------------- */

const ajaxurl = "/wp-admin/admin-ajax.php";

let currentCompletionCard = null;
let currentEditCard = null;

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

        ensureDeleteButtonsOnAllCards();
        enableDragAndDrop();
        attachKanbanClickHandlers();
        populateMainSectionDropdown();
        applyPriorityColoursToExistingCards();
        attachCompletedBoxHandlers();
    });
}

/* -----------------------------------------
   GLOBAL CLICK HANDLER (DELETE + EDIT)
----------------------------------------- */

document.addEventListener("click", (e) => {
    if (e.target.classList.contains("delete-btn")) {
        e.stopPropagation();

        const card = e.target.closest(".kanban-item");
        const target = card.dataset.target;

        card.remove();

        if (target && target.startsWith("#req-")) {
            const h3 = document.querySelector(target);
            if (h3) {
                const ul = h3.nextElementSibling;

                if (ul && ul.tagName === "UL") {
                    ul.querySelectorAll("li").forEach(li => {
                        const next = li.nextElementSibling;
                        if (next && next.classList.contains("completed-box")) {
                            next.remove();
                        }
                    });
                }

                h3.remove();
                if (ul && ul.tagName === "UL") ul.remove();
                cleanupEmptyMainSections();
            }
        }

        saveStateToDB();
        return;
    }

    if (e.target.classList.contains("edit-btn")) {
        e.stopPropagation();
        const card = e.target.closest(".kanban-item");
        openEditModal(card);
        return;
    }
});

/* -----------------------------------------
   ADD TASK MODAL
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

    // ⭐ CLEAN BULLET FORMAT — NO BOLD, NO ARROW, NO DETAILS
    li.textContent = title;

    // ⭐ Keep your matching rule (first word)
    li.dataset.cardTitle = title.split(/\s+/)[0].toLowerCase();

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

    // ⭐ Clean bullet: no bold, no arrow, no details
    li.textContent = title;

    // ⭐ Correct matching rule: first word only
    li.dataset.cardTitle = title.split(/\s+/)[0].toLowerCase();

    ul.appendChild(li);

    createKanbanCard(title, `#${subsectionId}`);
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
    li.dataset.cardTitle = firstSubTitle;
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

    const edit = document.createElement("button");
    edit.className = "edit-btn";
    edit.textContent = "Edit";
    card.appendChild(edit);

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
            if (e.target.classList.contains("delete-btn") || e.target.classList.contains("edit-btn")) return;

            const target = item.dataset.target;
            const el = document.querySelector(target);
            if (!el) return;

            el.classList.add("highlight");
            setTimeout(() => el.classList.remove("highlight"), 2000);

            el.scrollIntoView({ behavior: "smooth", block: "center" });

            // Auto-expand Work Done when clicking a DONE card
            if (item.parentElement.id === "done") {
                const req = document.querySelector(item.dataset.target);
                const ul = req.nextElementSibling;
                if (!ul || ul.tagName !== "UL") return;

                const title = item.firstChild.nodeValue.trim();
                let matchingLi = null;

                ul.querySelectorAll("li").forEach(li => {
                    if (li.dataset.cardTitle === title) {
                        matchingLi = li;
                    }
                });

                if (matchingLi) {
                    const box = matchingLi.nextElementSibling;
                    if (box && box.classList.contains("completed-box")) {
                        const content = box.querySelector(".completed-content");
                        if (content) content.style.display = "block";
                        box.classList.add("highlight");
                        setTimeout(() => box.classList.remove("highlight"), 2000);
                    }
                }
            }
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

            const col = item.parentElement.id;

            // Jira behaviour: leaving DONE removes Work Done
            if (col !== "done") {
                removeWorkDoneForCard(item);
            }

            // Entering DONE triggers completion modal if no summary exists
            if (col === "done" && !item.dataset.summary) {
                openCompletionModal(item);
            }

            saveStateToDB();
        });
    });

    columns.forEach(col => {
        col.addEventListener("dragover", e => {
            e.preventDefault();
            const dragging = document.querySelector(".dragging");
            if (dragging) col.appendChild(dragging);
        });
    });
}

/* -----------------------------------------
   REMOVE WORK DONE WHEN MOVING OUT OF DONE
----------------------------------------- */

function removeWorkDoneForCard(card) {
    const req = document.querySelector(card.dataset.target);
    if (!req) return;

    const ul = req.nextElementSibling;
    if (!ul || ul.tagName !== "UL") return;

    const title = card.childNodes[0].textContent.trim()
    ul.querySelectorAll("li").forEach(li => {
        if (li.dataset.cardTitle === title) {
            const next = li.nextElementSibling;
            if (next && next.classList.contains("completed-box")) {
                next.remove();
            }
        }
    });

    // Clear metadata
    card.dataset.summary = "";
    card.dataset.files = "";
    card.dataset.notes = "";
}
/* -----------------------------------------
   COMPLETION MODAL LOGIC
----------------------------------------- */

function openCompletionModal(card) {
    currentCompletionCard = card;

    // Extract the card title safely
    const title = card.childNodes[0].textContent.trim();

    // Show the title in the modal
    const titleEl = document.getElementById("completionTaskTitle");
    if (titleEl) {
        titleEl.textContent = title;
    }

    // Clear inputs
    document.getElementById("completionSummaryInput").value = "";
    document.getElementById("completionFilesInput").value = "";
    document.getElementById("completionNotesInput").value = "";

    // Show modal
    document.getElementById("completionModalBg").style.display = "flex";
}

document.getElementById("completionCancelBtn").onclick = () => {
    currentCompletionCard = null;
    document.getElementById("completionModalBg").style.display = "none";
};

document.getElementById("completionSaveBtn").onclick = () => {
    if (!currentCompletionCard) return;

    const summary = document.getElementById("completionSummaryInput").value.trim();
    const files = document.getElementById("completionFilesInput").value.trim();
    const notes = document.getElementById("completionNotesInput").value.trim();

    applyCompletionData(currentCompletionCard, summary, files, notes);

    document.getElementById("completionModalBg").style.display = "none";
    currentCompletionCard = null;

    saveStateToDB();
};


/* -----------------------------------------
   APPLY COMPLETION DATA (CREATE WORK DONE BOX)
----------------------------------------- */

function applyCompletionData(card, summary, files, notes) {
    const completedDate = new Date().toISOString().split("T")[0];

    // Save metadata on card
    card.dataset.summary = summary;
    card.dataset.files = files;
    card.dataset.notes = notes;
    card.dataset.completed = completedDate;

    const req = document.querySelector(card.dataset.target);
    if (!req) return;

    const ul = req.nextElementSibling;
    if (!ul || ul.tagName !== "UL") return;

    const title = card.childNodes[0].textContent.trim();
    let matchingLi = null;

    ul.querySelectorAll("li").forEach(li => {
        if (li.dataset.cardTitle === title) {
            matchingLi = li;
        }
    });

    if (!matchingLi) return;

    // Remove old Work Done box if exists
    const next = matchingLi.nextElementSibling;
    if (next && next.classList.contains("completed-box")) {
        next.remove();
    }

    // Create new Work Done box
    const box = document.createElement("div");
    box.className = "completed-box";

    const header = document.createElement("div");
    header.className = "completed-header";
    header.textContent = "Work Done (click to expand)";

    const content = document.createElement("div");
    content.className = "completed-content";
    content.style.display = "none";

    content.innerHTML = `
        <p><strong>Summary:</strong> ${summary || "—"}</p>
        <p><strong>Files Changed:</strong> ${files || "—"}</p>
        <p><strong>Date:</strong> ${completedDate}</p>
        ${notes ? `<p><strong>Notes:</strong> ${notes}</p>` : ""}
    `;

    box.appendChild(header);
    box.appendChild(content);

    matchingLi.insertAdjacentElement("afterend", box);

    attachCompletedBoxHandlers();
}

/* -----------------------------------------
   COMPLETED BOX HANDLERS (EXPAND/COLLAPSE)
----------------------------------------- */

function attachCompletedBoxHandlers() {
    document.querySelectorAll(".completed-box .completed-header").forEach(header => {
        header.onclick = () => {
            const content = header.nextElementSibling;
            if (!content) return;

            content.style.display =
                content.style.display === "none" ? "block" : "none";
        };
    });
}

/* -----------------------------------------
   EDIT MODAL LOGIC
----------------------------------------- */

function openEditModal(card) {
    currentEditCard = card;

    const req = document.querySelector(card.dataset.target);
    if (!req) return;

    const rawTitle = card.childNodes[0].textContent.trim();

    document.getElementById("editTitleInput").value = rawTitle;

    document.getElementById("editSummaryInput").value = card.dataset.summary || "";
    document.getElementById("editFilesInput").value = card.dataset.files || "";
    document.getElementById("editNotesInput").value = card.dataset.notes || "";

    document.getElementById("editModalBg").style.display = "flex";
}

document.getElementById("editCancelBtn").onclick = () => {
    currentEditCard = null;
    document.getElementById("editModalBg").style.display = "none";
};

document.getElementById("editSaveBtn").onclick = () => {
    if (!currentEditCard) return;

    const newTitle = document.getElementById("editTitleInput").value.trim();
    const newSummary = document.getElementById("editSummaryInput").value.trim();
    const newFiles = document.getElementById("editFilesInput").value.trim();
    const newNotes = document.getElementById("editNotesInput").value.trim();

    const oldTitle = currentEditCard.childNodes[0].textContent.trim();


    /* -------------------------
       UPDATE TITLE
    ------------------------- */
    if (newTitle) {
        currentEditCard.firstChild.nodeValue = newTitle;

        const req = document.querySelector(currentEditCard.dataset.target);
        if (req) {
            const span = req.querySelector("span");
            const text = req.textContent;
            const match = text.match(/^(\d+(\.\d+)*)\s+/);
            const prefix = match ? match[1] : "";

            req.innerHTML = `${prefix} ${newTitle}${span ? " " + span.outerHTML : ""}`;

            const ul = req.nextElementSibling;
            if (ul && ul.tagName === "UL") {
                ul.querySelectorAll("li").forEach(li => {
                    if (li.dataset.cardTitle === oldTitle) {
                        li.dataset.cardTitle = newTitle;
                        const strong = li.querySelector("strong");
                        if (strong) strong.textContent = newTitle;
                    }
                });
            }
        }
    }

    /* -------------------------
       UPDATE WORK DONE
    ------------------------- */
    if (newSummary || newFiles || newNotes) {
        applyCompletionData(currentEditCard, newSummary, newFiles, newNotes);
    } else {
        // Clear Work Done
        removeWorkDoneForCard(currentEditCard);
    }

    document.getElementById("editModalBg").style.display = "none";
    currentEditCard = null;

    saveStateToDB();
};

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
   ENSURE DELETE + EDIT BUTTONS
----------------------------------------- */

function ensureDeleteButtonsOnAllCards() {
    document.querySelectorAll(".kanban-item").forEach(card => {
        if (!card.querySelector(".delete-btn")) {
            const del = document.createElement("button");
            del.className = "delete-btn";
            del.textContent = "X";
            card.appendChild(del);
        }

        if (!card.querySelector(".edit-btn")) {
            const edit = document.createElement("button");
            edit.className = "edit-btn";
            edit.textContent = "Edit";
            card.appendChild(edit);
        }
    });
}

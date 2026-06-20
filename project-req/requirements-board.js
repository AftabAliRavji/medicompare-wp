/* -----------------------------------------
   CONFIG
----------------------------------------- */

const ajaxurl = "/wp-admin/admin-ajax.php";

let currentCompletionCard = null;
let currentWorkEditCard = null;
let currentTaskEditCard = null;

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
        updateWorkEditButtonsVisibility();
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

    if (e.target.classList.contains("task-edit-btn")) {
        e.stopPropagation();
        const card = e.target.closest(".kanban-item");
        openTaskEditModal(card);
        return;
    }

    if (e.target.classList.contains("work-edit-btn")) {
        e.stopPropagation();
        const card = e.target.closest(".kanban-item");
        openWorkEditModal(card);
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
    const priority = document.getElementById("prioritySelect").value;

    if (!title) return alert("Please enter a title.");

    if (mainSection === "__new__") {
        const newTitle = document.getElementById("newMainSectionInput").value.trim();
        if (!newTitle) return alert("Please enter a main section title.");
        createNewMainSection(newTitle, title, details, priority);

    } else if (addType === "subsection") {
        addNewSubsection(mainSection, title, details, priority);

    } else {
        const subsectionId = document.getElementById("subsectionSelect").value;
        addBulletPoint(subsectionId, title, details, priority);
    }

    modalBg.style.display = "none";
    saveStateToDB();
};

/* -----------------------------------------
   FIND SECTION NUMBER
----------------------------------------- */

function findSectionNumber(sectionTitle) {
    const h2s = [...document.querySelectorAll("#requirements h2")];
    return h2s.findIndex(h => h.textContent.trim() === sectionTitle) + 1;
}

/* -----------------------------------------
   ADD NEW SUBSECTION
----------------------------------------- */

function addNewSubsection(mainSection, title, details, priority) {
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

    let prioritySpan = "";
    if (priority === "high") prioritySpan = ` <span class="priority-high">HIGH PRIORITY</span>`;
    if (priority === "med")  prioritySpan = ` <span class="priority-med">MED/HIGH PRIORITY</span>`;
    if (priority === "low")  prioritySpan = ` <span class="priority-low">LOW PRIORITY</span>`;

    h3.innerHTML = `${newHeading}${prioritySpan}`;

    const ul = document.createElement("ul");
    const li = document.createElement("li");

    li.textContent = title;
    li.dataset.cardTitle = title;

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

    createKanbanCard(title, `#${newId}`, details, priority);
}

/* -----------------------------------------
   ADD BULLET POINT
----------------------------------------- */

function addBulletPoint(subsectionId, title, details, priority) {
    const h3 = document.getElementById(subsectionId);
    const ul = h3.nextElementSibling;

    const li = document.createElement("li");
    li.textContent = title;
    li.dataset.cardTitle = title;

    ul.appendChild(li);

    createKanbanCard(title, `#${subsectionId}`, details, priority);
}

/* -----------------------------------------
   CREATE NEW MAIN SECTION
----------------------------------------- */

function createNewMainSection(sectionTitle, firstSubTitle, details, priority) {
    const h2s = [...document.querySelectorAll("#requirements h2")];
    const nextMainNumber = h2s.length + 1;

    const h2 = document.createElement("h2");
    h2.textContent = `${nextMainNumber}. ${sectionTitle}`;

    const newId = `req-${nextMainNumber}-1`;

    let prioritySpan = "";
    if (priority === "high") prioritySpan = ` <span class="priority-high">HIGH PRIORITY</span>`;
    if (priority === "med")  prioritySpan = ` <span class="priority-med">MED/HIGH PRIORITY</span>`;
    if (priority === "low")  prioritySpan = ` <span class="priority-low">LOW PRIORITY</span>`;

    const h3 = document.createElement("h3");
    h3.id = newId;
    h3.innerHTML = `${nextMainNumber}.1 ${firstSubTitle}${prioritySpan}`;

    const ul = document.createElement("ul");
    const li = document.createElement("li");
    li.textContent = firstSubTitle;
    li.dataset.cardTitle = firstSubTitle;
    ul.appendChild(li);

    const req = document.getElementById("requirements");
    req.appendChild(document.createElement("div")).className = "section-divider";
    req.appendChild(h2);
    req.appendChild(h3);
    req.appendChild(ul);

    createKanbanCard(firstSubTitle, `#${newId}`, details, priority);

    saveStateToDB();
    populateMainSectionDropdown();
}

/* -----------------------------------------
   CREATE KANBAN CARD
----------------------------------------- */

function createKanbanCard(title, target, details, priority) {
    const card = document.createElement("div");
    card.className = "kanban-item";
    card.draggable = true;
    card.dataset.target = target;
    card.textContent = title;

    if (details) {
        card.dataset.description = details;
    } else {
        card.dataset.description = "";
    }

    if (priority) {
        card.dataset.priority = priority;
    }

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

    const taskEdit = document.createElement("button");
    taskEdit.className = "task-edit-btn";
    taskEdit.textContent = "Edit";
    card.appendChild(taskEdit);

    const workEdit = document.createElement("button");
    workEdit.className = "work-edit-btn";
    workEdit.textContent = "Work";
    card.appendChild(workEdit);

    document.getElementById("todo").appendChild(card);

    enableDragAndDrop();
    attachKanbanClickHandlers();
    updateWorkEditButtonsVisibility();
}

/* -----------------------------------------
   KANBAN CLICK → SCROLL + HIGHLIGHT
----------------------------------------- */

function attachKanbanClickHandlers() {
    document.querySelectorAll(".kanban-item").forEach(item => {
        item.onclick = (e) => {
            if (
                e.target.classList.contains("delete-btn") ||
                e.target.classList.contains("task-edit-btn") ||
                e.target.classList.contains("work-edit-btn")
            ) return;

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

                const title = item.childNodes[0].textContent.trim();
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

            // Leaving DONE removes Work Done
            if (col !== "done") {
                removeWorkDoneForCard(item);
            }

            // Entering DONE triggers completion modal if no summary exists
            if (col === "done" && !item.dataset.summary) {
                openCompletionModal(item);
            }

            updateWorkEditButtonsVisibility();
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
   SHOW/HIDE WORK EDIT BUTTONS
----------------------------------------- */

function updateWorkEditButtonsVisibility() {
    document.querySelectorAll(".kanban-item").forEach(card => {
        const workBtn = card.querySelector(".work-edit-btn");
        if (!workBtn) return;

        if (card.parentElement && card.parentElement.id === "done") {
            workBtn.style.display = "inline-block";
        } else {
            workBtn.style.display = "none";
        }
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

    const title = card.childNodes[0].textContent.trim();
    ul.querySelectorAll("li").forEach(li => {
        if (li.dataset.cardTitle === title) {
            const next = li.nextElementSibling;
            if (next && next.classList.contains("completed-box")) {
                next.remove();
            }
        }
    });

    card.dataset.summary = "";
    card.dataset.files = "";
    card.dataset.notes = "";
    card.dataset.completed = "";
}

/* -----------------------------------------
   COMPLETION MODAL LOGIC
----------------------------------------- */

function openCompletionModal(card) {
    currentCompletionCard = card;

    const title = card.childNodes[0].textContent.trim();

    const titleEl = document.getElementById("completionTaskTitle");
    if (titleEl) {
        titleEl.textContent = title;
    }

    document.getElementById("completionSummaryInput").value = "";
    document.getElementById("completionFilesInput").value = "";
    document.getElementById("completionNotesInput").value = "";

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

    const next = matchingLi.nextElementSibling;
    if (next && next.classList.contains("completed-box")) {
        next.remove();
    }

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
   EDIT WORK DONE MODAL LOGIC
----------------------------------------- */

function openWorkEditModal(card) {
    currentWorkEditCard = card;

    document.getElementById("editSummaryInput").value = card.dataset.summary || "";
    document.getElementById("editFilesInput").value = card.dataset.files || "";
    document.getElementById("editNotesInput").value = card.dataset.notes || "";

    document.getElementById("editModalBg").style.display = "flex";
}

document.getElementById("editCancelBtn").onclick = () => {
    currentWorkEditCard = null;
    document.getElementById("editModalBg").style.display = "none";
};

document.getElementById("editSaveBtn").onclick = () => {
    if (!currentWorkEditCard) return;

    const newSummary = document.getElementById("editSummaryInput").value.trim();
    const newFiles = document.getElementById("editFilesInput").value.trim();
    const newNotes = document.getElementById("editNotesInput").value.trim();

    if (newSummary || newFiles || newNotes) {
        applyCompletionData(currentWorkEditCard, newSummary, newFiles, newNotes);
    } else {
        removeWorkDoneForCard(currentWorkEditCard);
    }

    document.getElementById("editModalBg").style.display = "none";
    currentWorkEditCard = null;

    saveStateToDB();
};

/* -----------------------------------------
   EDIT TASK MODAL LOGIC
----------------------------------------- */

function openTaskEditModal(card) {
    currentTaskEditCard = card;

    const title = card.childNodes[0].textContent.trim();
    const description = card.dataset.description || "";
    const priority = card.dataset.priority || inferPriorityFromClasses(card);

    document.getElementById("taskEditTitleInput").value = title;
    document.getElementById("taskEditDescriptionInput").value = description;
    document.getElementById("taskEditPrioritySelect").value = priority;

    populateTaskEditSubsectionDropdown(card);

    document.getElementById("taskEditModalBg").style.display = "flex";
}

function inferPriorityFromClasses(card) {
    if (card.classList.contains("high")) return "high";
    if (card.classList.contains("med")) return "med";
    if (card.classList.contains("low")) return "low";
    return "low";
}

function populateTaskEditSubsectionDropdown(card) {
    const select = document.getElementById("taskEditSubsectionSelect");
    select.innerHTML = "";

    const h3s = document.querySelectorAll("#requirements h3");
    const currentTarget = card.dataset.target.replace("#", "");

    h3s.forEach(h3 => {
        const opt = document.createElement("option");
        opt.value = h3.id;
        opt.textContent = h3.textContent.replace(/<[^>]*>/g, "").trim();
        if (h3.id === currentTarget) opt.selected = true;
        select.appendChild(opt);
    });
}

document.getElementById("taskEditCancelBtn").onclick = () => {
    currentTaskEditCard = null;
    document.getElementById("taskEditModalBg").style.display = "none";
};

document.getElementById("taskEditSaveBtn").onclick = () => {
    if (!currentTaskEditCard) return;

    const newTitle = document.getElementById("taskEditTitleInput").value.trim();
    const newDescription = document.getElementById("taskEditDescriptionInput").value.trim();
    const newPriority = document.getElementById("taskEditPrioritySelect").value;
    const newSubsectionId = document.getElementById("taskEditSubsectionSelect").value;

    const oldTitle = currentTaskEditCard.childNodes[0].textContent.trim();
    const oldTarget = currentTaskEditCard.dataset.target;

    if (newTitle) {
        currentTaskEditCard.firstChild.nodeValue = newTitle;
    }

    currentTaskEditCard.dataset.description = newDescription || "";
    currentTaskEditCard.dataset.priority = newPriority;

    currentTaskEditCard.classList.remove("high", "med", "low");
    if (newPriority === "high") currentTaskEditCard.classList.add("high");
    if (newPriority === "med") currentTaskEditCard.classList.add("med");
    if (newPriority === "low") currentTaskEditCard.classList.add("low");

    const oldReq = document.querySelector(oldTarget);
    if (oldReq) {
        const span = oldReq.querySelector("span");
        const text = oldReq.textContent;
        const match = text.match(/^(\d+(\.\d+)*)\s+/);
        const prefix = match ? match[1] : "";

        oldReq.innerHTML = `${prefix} ${newTitle}${span ? " " + span.outerHTML : ""}`;
    }

    const oldReqNode = document.querySelector(oldTarget);
    const oldUl = oldReqNode ? oldReqNode.nextElementSibling : null;
    let movedLi = null;
    let movedBox = null;

    if (oldUl && oldUl.tagName === "UL") {
        oldUl.querySelectorAll("li").forEach(li => {
            if (!movedLi && li.dataset.cardTitle === oldTitle) {
                movedLi = li;
                const next = li.nextElementSibling;
                if (next && next.classList.contains("completed-box")) {
                    movedBox = next;
                }
            }
        });
    }

    if (movedLi) {
        movedLi.dataset.cardTitle = newTitle;
        movedLi.textContent = newTitle;
    }

    if (newSubsectionId && oldTarget !== `#${newSubsectionId}`) {
        const newReq = document.getElementById(newSubsectionId);
        const newUl = newReq ? newReq.nextElementSibling : null;

        if (newUl && newUl.tagName === "UL") {
            if (movedLi) {
                newUl.appendChild(movedLi);
                if (movedBox) {
                    newUl.appendChild(movedBox);
                }
            }
            currentTaskEditCard.dataset.target = `#${newSubsectionId}`;
        }
    }

    const newReqForPriority = document.querySelector(currentTaskEditCard.dataset.target);
    if (newReqForPriority) {
        let prioritySpan = "";
        if (newPriority === "high") prioritySpan = ` <span class="priority-high">HIGH PRIORITY</span>`;
        if (newPriority === "med")  prioritySpan = ` <span class="priority-med">MED/HIGH PRIORITY</span>`;
        if (newPriority === "low")  prioritySpan = ` <span class="priority-low">LOW PRIORITY</span>`;

        const text = newReqForPriority.textContent;
        const match = text.match(/^(\d+(\.\d+)*)\s+/);
        const prefix = match ? match[1] : "";
        const titleOnly = newTitle || text.replace(/^(\d+(\.\d+)*)\s+/, "").trim();

        newReqForPriority.innerHTML = `${prefix} ${titleOnly}${prioritySpan}`;
    }

    document.getElementById("taskEditModalBg").style.display = "none";
    currentTaskEditCard = null;

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

        if (!card.querySelector(".task-edit-btn")) {
            const taskEdit = document.createElement("button");
            taskEdit.className = "task-edit-btn";
            taskEdit.textContent = "Edit";
            card.appendChild(taskEdit);
        }

        if (!card.querySelector(".work-edit-btn")) {
            const workEdit = document.createElement("button");
            workEdit.className = "work-edit-btn";
            workEdit.textContent = "Work";
            card.appendChild(workEdit);
        }
    });

    updateWorkEditButtonsVisibility();
}

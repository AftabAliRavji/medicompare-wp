/* 
   ONE-TIME MIGRATION SCRIPT
   ------------------------------------
   Reads LocalStorage → Saves to DB → Done
*/

const ajaxurl = "/wp-admin/admin-ajax.php?action=save_requirements_board";

jQuery(function () {

    window.migrateLocalStorageToDB = function () {

        const kanban = localStorage.getItem("kanbanHTML");
        const requirements = localStorage.getItem("requirementsHTML");

        if (!kanban || !requirements) {
            alert("No LocalStorage data found. Load the OLD index.html first.");
            return;
        }

        const board = {
            kanbanHTML: kanban,
            requirementsHTML: requirements
        };

        console.log("Sending board_json:", board);

        // SEND AS FORM-ENCODED (REQUIRED BY WORDPRESS)
        jQuery.ajax({
            url: ajaxurl,
            method: "POST",
            data: {
                board_json: JSON.stringify(board)
            },
            success: function (response) {
                console.log("Server response:", response);

                if (response && response.success) {
                    alert("Migration complete! Data saved to DB.");
                } else {
                    alert("Migration failed. Check console.");
                }
            },
            error: function (xhr) {
                console.error("AJAX error:", xhr.responseText);
                alert("Migration failed (AJAX error). Check console.");
            }
        });
    };

});
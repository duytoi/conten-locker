jQuery(document).ready(function($) {
    // Constants
    const SELECTORS = {
        bulkActions: '#doaction, #doaction2',
        checkboxes: 'input[name="bulk-delete[]"]',
        deleteItem: '.delete-item',
        notice: '.notice',
        loadingOverlay: '.wcl-loading-overlay',
        table: '.wp-list-table'
    };

    // Handle bulk actions
    $(SELECTORS.bulkActions).click(function(e) {
        e.preventDefault();
        
        const $button = $(this);
        const $form = $button.closest('form');
        const $select = $button.prev('select');
        const action = $select.val();

        if (action === '-1') {
            return false;
        }

        const $checked = $form.find(SELECTORS.checkboxes + ':checked');
        if (!$checked.length) {
            showNotice('error', wcl_admin.messages.no_items);
            return false;
        }

        if (action === 'delete') {
            handleBulkDelete($checked);
        }
    });

    // Handle single delete links
    $(SELECTORS.deleteItem).on('click', function(e) {
        e.preventDefault();
        handleSingleDelete($(this));
    });

    // Handle bulk delete
    function handleBulkDelete($checked) {
        if (!confirm(wcl_admin.messages.confirm_delete)) {
            return false;
        }

        const downloadIds = $checked.map(function() {
            return $(this).val();
        }).get();

        deleteItems(downloadIds, true);
    }

    // Handle single delete
    function handleSingleDelete($link) {
        if (!confirm(wcl_admin.messages.confirm_delete)) {
            return false;
        }

        const downloadId = $link.data('id');
        const $row = $link.closest('tr');
        
        deleteItems([downloadId], false, $row);
    }

    // Generic delete function
    function deleteItems(ids, isBulk, $row = null) {
        showLoadingOverlay();

        $.ajax({
            url: wcl_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'wcl_delete_downloads',
                _ajax_nonce: wcl_admin.nonce,
                ids: ids
            },
            success: function(response) {
                handleDeleteSuccess(response, isBulk, ids.length, $row);
            },
            error: function(xhr) {
                handleDeleteError(xhr);
            },
            complete: function() {
                hideLoadingOverlay();
            }
        });
    }

    // Handle successful deletion
    function handleDeleteSuccess(response, isBulk, count, $row) {
        if (response.success) {
            if (isBulk) {
                window.location.href = `${wcl_admin.list_url}&message=deleted&count=${count}`;
            } else {
                $row.fadeOut(400, function() {
                    $(this).remove();
                    updateTableCounts();
                    showNotice('success', wcl_admin.messages.delete_success);
                });
            }
        } else {
            showNotice('error', response.data?.message || wcl_admin.messages.error);
        }
    }

    // Handle deletion error
    function handleDeleteError(xhr) {
        const errorMessage = xhr.responseJSON?.data?.message || wcl_admin.messages.error;
        showNotice('error', errorMessage);
    }

    // Loading overlay functions
    function showLoadingOverlay() {
        if (!$(SELECTORS.loadingOverlay).length) {
            $('body').append(`
                <div class="wcl-loading-overlay">
                    <div class="wcl-loading-content">
                        <span class="spinner is-active"></span>
                        ${wcl_admin.messages.deleting}
                    </div>
                </div>
            `);
        }
    }

    function hideLoadingOverlay() {
        $(SELECTORS.loadingOverlay).remove();
    }

    // Function to show notices
    function showNotice(type, message) {
        const $notice = $(`
            <div class="notice notice-${type} is-dismissible">
                <p>${message}</p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">Dismiss this notice</span>
                </button>
            </div>
        `);

        const $existing = $('.wrap .notice');
        
        if ($existing.length) {
            $existing.replaceWith($notice);
        } else {
            $('.wrap h1').after($notice);
        }

        // Auto dismiss after 3 seconds
        setTimeout(() => {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 3000);
    }

    // Function to update table counts
    function updateTableCounts() {
        const $table = $(SELECTORS.table);
        const totalItems = $table.find('tbody tr').not('.no-items').length;
        
        if (totalItems === 0) {
            $table.find('tbody').html(`
                <tr class="no-items">
                    <td class="colspanchange" colspan="6">
                        ${wcl_admin.messages.no_items}
                    </td>
                </tr>
            `);
        }
        
        // Update counts in tablenav
        $('.tablenav .displaying-num').text(
            `${totalItems} ${totalItems === 1 ? 'item' : 'items'}`
        );
    }

    // Handle notice dismissal
    $(document).on('click', '.notice-dismiss', function() {
        $(this).closest(SELECTORS.notice).fadeOut(300, function() {
            $(this).remove();
        });
    });
});
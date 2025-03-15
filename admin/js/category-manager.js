jQuery(document).ready(function($) {
    'use strict';

    const CategoryManager = {
        init: function() {
            this.bindEvents();
            this.initModal();
        },

        bindEvents: function() {
            // Bind các sự kiện cho buttons
            $('#add-new-category').on('click', this.openAddModal);
            $('.edit-category').on('click', this.openEditModal);
            $('.delete-category').on('click', this.deleteCategory);
            $('#wcl-category-form').on('submit', this.saveCategory);
            $('.wcl-modal-close, #cancel-category').on('click', this.closeModal);
        },

        initModal: function() {
            // Đóng modal khi click outside
            $(window).on('click', function(e) {
                if ($(e.target).is('#category-modal')) {
                    CategoryManager.closeModal();
                }
            });

            // Ngăn chặn form submit mặc định
            $('#wcl-category-form').on('submit', function(e) {
                e.preventDefault();
            });
        },

        openAddModal: function(e) {
            e.preventDefault();
            $('#modal-title').text(wcl_ajax.i18n.addNewCategory || 'Add New Category');
            $('#wcl-category-form')[0].reset();
            $('#category-id').val('0');
            $('#category-modal').show();
        },

        openEditModal: function(e) {
            e.preventDefault();
            const id = $(this).data('id');
            
            $.ajax({
                url: wcl_ajax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'wcl_get_category',
                    nonce: wcl_ajax.nonce,
                    id: id
                },
                success: function(response) {
                    if (response.success) {
                        const category = response.data;
                        $('#modal-title').text(wcl_ajax.i18n.editCategory || 'Edit Category');
                        $('#category-id').val(category.id);
                        $('#category-name').val(category.name);
                        $('#category-description').val(category.description);
                        $('#category-parent').val(category.parent_id);
                        $('#category-modal').show();
                    } else {
                        alert(response.data.message || 'Error loading category');
                    }
                },
                error: function() {
                    alert('Server error occurred');
                }
            });
        },

        saveCategory: function(e) {
            e.preventDefault();
            const id = $('#category-id').val();
            const isNew = id === '0';
            const action = isNew ? 'wcl_add_category' : 'wcl_edit_category';

            const formData = {
                action: action,
                nonce: wcl_ajax.nonce,
                id: id,
                name: $('#category-name').val(),
                description: $('#category-description').val(),
                parent_id: $('#category-parent').val()
            };

            $.ajax({
                url: wcl_ajax.ajaxurl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message || 'Error saving category');
                    }
                },
                error: function() {
                    alert('Server error occurred');
                }
            });
        },

        deleteCategory: function(e) {
            e.preventDefault();
            const id = $(this).data('id');
            const downloads = $(this).data('downloads') || 0;
            
            if (downloads > 0) {
                alert(wcl_ajax.i18n.cannotDeleteCategory || 'Cannot delete category with downloads');
                return;
            }

            if (confirm(wcl_ajax.i18n.confirmDelete || 'Are you sure you want to delete this category?')) {
                $.ajax({
                    url: wcl_ajax.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wcl_delete_category',
                        nonce: wcl_ajax.nonce,
                        id: id
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert(response.data.message || 'Error deleting category');
                        }
                    },
                    error: function() {
                        alert('Server error occurred');
                    }
                });
            }
        },

        closeModal: function() {
            $('#category-modal').hide();
            $('#wcl-category-form')[0].reset();
        }
    };

    // Initialize the CategoryManager
    CategoryManager.init();
});
humhub.module('thiscoveryNavigation', function (module, require, $) {
    var client = require('client');

    var csrf = function () {
        var data = {};
        if (window.yii && yii.getCsrfParam) {
            data[yii.getCsrfParam()] = yii.getCsrfToken();
        }
        return data;
    };

    var post = function (url, data) {
        return client.post(url, { data: $.extend({}, csrf(), data || {}) });
    };

    var responseTree = function (res) {
        if (!res) {
            return null;
        }
        if (res.tree) {
            return res;
        }
        if (res.data && res.data.tree) {
            return res.data;
        }
        return null;
    };

    var afterSave = function (res) {
        var data = responseTree(res);
        if (!data) {
            return false;
        }
        try {
            require('ui.status').success(data.message || 'Saved');
        } catch (e) {}
        window.setTimeout(function () {
            window.location.reload();
        }, 250);
        return true;
    };

    var saveFailed = function () {
        try {
            require('ui.status').error('Could not save the menu. Try again.');
        } catch (e) {
            window.alert('Could not save the menu. Try again.');
        }
    };

    var collectTree = function ($list) {
        var nodes = [];
        $list.children('li[data-id]').each(function () {
            var $li = $(this);
            var childList = $li.children('ol');
            nodes.push({
                id: parseInt($li.attr('data-id'), 10),
                children: childList.length ? collectTree(childList) : []
            });
        });
        return nodes;
    };

    var placementOptions = {};

    var placeLabel = function (place) {
        return placementOptions[place] || placementOptions.hamburger || 'Hamburger menu';
    };

    var typeLabel = function (type) {
        if (type === 'group') {
            return 'Group';
        }
        if (type === 'url') {
            return 'URL';
        }
        return type || 'item';
    };

    var renderNode = function (node, isRoot) {
        var children = node.children || [];
        var place = node.mobile_placement || 'hamburger';
        var html = '<li data-id="' + node.id + '" data-type="' + (node.type || '') + '">'
            + '<div class="tn-tree-row" draggable="true">'
            + '<span class="tn-tree-row__handle" aria-hidden="true">::</span>'
            + '<button type="button" class="tn-tree-row__label" data-tn-select>'
            + $('<span>').text(node.label || 'Untitled').html()
            + ' <small>' + typeLabel(node.type) + '</small>'
            + '</button>'
            + (isRoot ? '<span class="tn-tree-row__place">' + $('<span>').text(placeLabel(place)).html() + '</span>' : '')
            + '</div>'
            + '<ol></ol>'
            + '</li>';
        var $li = $(html);
        $li.data('node', node);
        var $ol = $li.children('ol');
        children.forEach(function (child) {
            $ol.append(renderNode(child, false));
        });
        return $li;
    };

    var renderTree = function ($root, tree) {
        $root.empty();
        (tree || []).forEach(function (node) {
            $root.append(renderNode(node, true));
        });
    };

    var renderMobile = function ($list, tree) {
        $list.empty();
        var roots = tree || [];
        if (!roots.length) {
            $list.append('<li class="tn-mobile-list__empty">No top-level items yet. Add items to the tree first.</li>');
            return;
        }
        roots.forEach(function (node) {
            var $li = $('<li class="tn-mobile-list__item">');
            var $label = $('<span class="tn-mobile-list__label">').text(node.label || 'Untitled');
            if (node.enabled === false) {
                $label.append($('<small>').text(' (off)'));
            }
            var $select = $('<select class="form-control form-control-sm" data-tn-place-id="' + node.id + '">');
            Object.keys(placementOptions).forEach(function (value) {
                var $opt = $('<option>').attr('value', value).text(placementOptions[value]);
                if ((node.mobile_placement || 'hamburger') === value) {
                    $opt.prop('selected', true);
                }
                $select.append($opt);
            });
            $li.append($label).append($select);
            $list.append($li);
        });
    };

    var renderUnused = function ($list, unused) {
        $list.empty();
        if (!unused || !unused.length) {
            $list.append('<li class="tn-unused__empty">Nothing waiting to be added.</li>');
            return;
        }
        unused.forEach(function (item) {
            var $li = $('<li class="tn-unused__item">');
            $li.append($('<span>').text(item.label + ' (' + item.type + ')'));
            var $btn = $('<button type="button" class="btn btn-sm btn-light">Add</button>');
            $btn.on('click', function () {
                var $admin = $('[data-tn-admin]');
                post($admin.data('add-url'), { source_key: item.key }).then(function (res) {
                    if (!afterSave(res)) {
                        saveFailed();
                    }
                }).catch(saveFailed);
            });
            $li.append($btn);
            $list.append($li);
        });
    };

    var bindDrag = function ($tree) {
        var dragEl = null;

        var clearDrop = function () {
            $tree.removeClass('is-drop-root');
            $tree.find('.is-drop-before, .is-drop-child, .is-drop-after, .is-drop-outdent').removeClass('is-drop-before is-drop-child is-drop-after is-drop-outdent');
        };

        var depthOf = function ($li) {
            return $li.parents('ol').length;
        };

        var subtreeLevels = function (el) {
            var base = $(el).parents('ol').length;
            var max = 1;
            $(el).find('li').each(function () {
                var extra = $(this).parents('ol').length - base;
                if (extra + 1 > max) {
                    max = extra + 1;
                }
            });
            return max;
        };

        var canPlaceAt = function (depth) {
            return depth + (subtreeLevels(dragEl) - 1) <= 3;
        };

        var zoneForRow = function (rowEl, e) {
            var rect = rowEl.getBoundingClientRect();
            var y = (e.originalEvent.clientY - rect.top) / Math.max(rect.height, 1);
            var x = e.originalEvent.clientX - rect.left;
            var $li = $(rowEl).closest('li');
            if (depthOf($li) > 1 && x < 36) {
                return 'outdent';
            }
            if (y < 0.3) {
                return 'before';
            }
            if (y > 0.7) {
                return 'after';
            }
            return 'child';
        };

        $tree.on('dragstart', '.tn-tree-row', function (e) {
            dragEl = $(this).closest('li')[0];
            e.originalEvent.dataTransfer.effectAllowed = 'move';
            e.originalEvent.dataTransfer.setData('text/plain', $(dragEl).attr('data-id'));
            $(dragEl).addClass('is-dragging');
        });
        $tree.on('dragend', '.tn-tree-row', function () {
            $(dragEl).removeClass('is-dragging');
            dragEl = null;
            clearDrop();
        });
        $tree.on('dragover', '.tn-tree-row', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (!dragEl) {
                return;
            }
            var $li = $(this).closest('li');
            if ($li[0] === dragEl || $.contains(dragEl, $li[0])) {
                return;
            }
            clearDrop();
            var zone = zoneForRow(this, e);
            if (zone === 'outdent') {
                $li.addClass('is-drop-outdent');
                return;
            }
            $li.addClass('is-drop-' + zone);
        });
        $tree.on('dragover', function (e) {
            if (!dragEl || this !== e.target) {
                return;
            }
            e.preventDefault();
            clearDrop();
            $tree.addClass('is-drop-root');
        });
        $tree.on('drop', '.tn-tree-row', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (!dragEl) {
                return;
            }
            var $target = $(this).closest('li');
            if ($target[0] === dragEl || $.contains(dragEl, $target[0])) {
                clearDrop();
                return;
            }
            var zone = $target.hasClass('is-drop-outdent') ? 'outdent'
                : $target.hasClass('is-drop-before') ? 'before'
                : $target.hasClass('is-drop-after') ? 'after'
                : $target.hasClass('is-drop-child') ? 'child'
                : zoneForRow(this, e);

            if (zone === 'outdent') {
                var $parent = $target.parent().closest('li');
                if ($parent.length && canPlaceAt(depthOf($parent))) {
                    $parent.after(dragEl);
                } else if (canPlaceAt(1)) {
                    $tree.append(dragEl);
                }
            } else if (zone === 'child') {
                if (canPlaceAt(depthOf($target) + 1)) {
                    $target.children('ol').append(dragEl);
                }
            } else if (zone === 'before') {
                if (canPlaceAt(depthOf($target))) {
                    $target.before(dragEl);
                }
            } else if (canPlaceAt(depthOf($target))) {
                $target.after(dragEl);
            }
            clearDrop();
        });
        $tree.on('drop', function (e) {
            if (!dragEl || this !== e.target) {
                return;
            }
            e.preventDefault();
            if (canPlaceAt(1)) {
                $tree.append(dragEl);
            }
            clearDrop();
        });
    };

    var initAdmin = function () {
        var $admin = $('[data-tn-admin]');
        if (!$admin.length || $admin.data('tnReady')) {
            return;
        }
        $admin.data('tnReady', '1');
        var $tree = $admin.find('[data-tn-tree]');
        var $unused = $admin.find('[data-tn-unused]');
        var $editor = $admin.find('[data-tn-editor]');
        var $form = $admin.find('[data-tn-edit-form]');
        var $mobileList = $admin.find('[data-tn-mobile-list]');
        var tree = [];
        var unused = [];
        try {
            tree = JSON.parse($admin.attr('data-tree') || '[]');
            unused = JSON.parse($admin.attr('data-unused') || '[]');
            placementOptions = JSON.parse($admin.attr('data-placement-options') || '{}');
        } catch (e) {}
        renderTree($tree, tree);
        renderUnused($unused, unused);
        renderMobile($mobileList, tree);
        bindDrag($tree);

        var applyTree = function (data) {
            tree = data.tree || tree;
            renderTree($tree, tree);
            renderMobile($mobileList, tree);
            if (data.unused) {
                unused = data.unused;
                renderUnused($unused, unused);
            }
        };

        $admin.on('change', '[data-tn-place-id]', function () {
            var $select = $(this);
            $select.prop('disabled', true);
            post($admin.data('placement-url'), {
                id: $select.attr('data-tn-place-id'),
                mobile_placement: $select.val()
            }).then(function (res) {
                var data = responseTree(res);
                $select.prop('disabled', false);
                if (!data) {
                    saveFailed();
                    return;
                }
                try {
                    require('ui.status').success(data.message || 'Saved');
                } catch (e) {}
                applyTree(data);
            }).catch(function () {
                $select.prop('disabled', false);
                saveFailed();
            });
        });

        $admin.on('click', '[data-tn-select]', function () {
            var $li = $(this).closest('li');
            var node = $li.data('node') || {};
            $form.find('[name=id]').val(node.id || '');
            $form.find('[name=label]').val(node.label || '');
            $form.find('[name=icon]').val(node.icon || '');
            $form.find('[name=url]').val(node.url || '');
            $form.find('[name=visibility]').val(node.visibility || 'all');
            $form.find('[name=mobile_placement]').val(node.mobile_placement || 'hamburger');
            $form.find('[name=new_window]').prop('checked', !!node.new_window);
            $form.find('[name=enabled]').prop('checked', node.enabled !== false);
            $form.find('[data-tn-url-field]').toggle(node.type === 'url');
            $form.find('[data-tn-placement-field]').toggle($li.parent().is('[data-tn-tree]'));
            $admin.find('[data-tn-promote]').prop('hidden', $li.parent().is('[data-tn-tree]'));
            $editor.removeAttr('hidden');
        });

        $admin.on('click', '[data-tn-save]', function () {
            var $btn = $(this);
            $btn.prop('disabled', true);
            post($admin.data('save-url'), { tree: JSON.stringify(collectTree($tree)) }).then(function (res) {
                if (afterSave(res)) {
                    return;
                }
                $btn.prop('disabled', false);
                saveFailed();
            }).catch(function () {
                $btn.prop('disabled', false);
                saveFailed();
            });
        });

        $admin.on('click', '[data-tn-add]', function () {
            var type = $(this).attr('data-tn-add');
            var label = window.prompt(type === 'group' ? 'Group label' : 'Link label');
            if (!label) {
                return;
            }
            var payload = { type: type, label: label };
            if (type === 'url') {
                payload.url = window.prompt('URL', '/') || '/';
            }
            post($admin.data('create-url'), payload).then(function (res) {
                if (!afterSave(res)) {
                    saveFailed();
                }
            }).catch(saveFailed);
        });

        $form.on('submit', function (e) {
            e.preventDefault();
            var payload = {
                id: $form.find('[name=id]').val(),
                label: $form.find('[name=label]').val(),
                icon: $form.find('[name=icon]').val(),
                url: $form.find('[name=url]').val(),
                visibility: $form.find('[name=visibility]').val(),
                mobile_placement: $form.find('[name=mobile_placement]').val(),
                new_window: $form.find('[name=new_window]').is(':checked') ? 1 : 0,
                enabled: $form.find('[name=enabled]').is(':checked') ? 1 : 0
            };
            post($admin.data('update-url'), payload).then(function (res) {
                if (!afterSave(res)) {
                    saveFailed();
                }
            }).catch(saveFailed);
        });

        $admin.on('click', '[data-tn-promote]', function () {
            var id = $form.find('[name=id]').val();
            if (!id) {
                return;
            }
            var $li = $tree.find('li[data-id="' + id + '"]');
            if ($li.length) {
                $tree.append($li);
                $(this).prop('hidden', true);
            }
        });

        $admin.on('click', '[data-tn-delete]', function () {
            var id = $form.find('[name=id]').val();
            if (!id || !window.confirm('Remove this item from the menu?')) {
                return;
            }
            post($admin.data('delete-url'), { id: id }).then(function (res) {
                if (!afterSave(res)) {
                    saveFailed();
                }
            }).catch(saveFailed);
        });
    };

    var navRoots = function () {
        return $('#top-menu-nav, #top-menu-mobile-nav-slot, #top-menu-floating-bar');
    };

    var isCompactNav = function () {
        return window.matchMedia('(max-width: 767.98px)').matches;
    };

    var closeAll = function ($scope) {
        ($scope && $scope.length ? $scope : navRoots())
            .find('.tn-item.is-open')
            .removeClass('is-open')
            .children('.tn-link')
            .attr('aria-expanded', 'false');
    };

    var alignSubmenu = function ($item) {
        $item.removeClass('tn-align-start');
        var sub = $item.children('.tn-submenu')[0];
        if (!sub || isCompactNav()) {
            return;
        }
        var rect = sub.getBoundingClientRect();
        if (rect.right > window.innerWidth - 8) {
            $item.addClass('tn-align-start');
        }
    };

    var initNav = function () {
        var $roots = navRoots();
        if (!$roots.find('.tn-item').length) {
            return;
        }
        $roots.addClass('tn-nav');
        $roots.off('.tnNav');
        $(document).off('.tnNav');

        var toggleSelector = '.tn-item.tn-has-children > .tn-link';

        $roots.on('click.tnNav', toggleSelector, function (e) {
            if ($(this).attr('data-action-click')) {
                return;
            }
            var $link = $(this);
            var href = $link.attr('href');
            var isToggleOnly = !href || href === '#';
            if (isToggleOnly || isCompactNav()) {
                e.preventDefault();
                e.stopPropagation();
                var $item = $link.parent('.tn-item');
                if (!$item.length) {
                    $item = $link.closest('.tn-item');
                }
                var open = !$item.hasClass('is-open');
                $item.siblings('.tn-item.is-open').removeClass('is-open').children('.tn-link').attr('aria-expanded', 'false');
                $item.toggleClass('is-open', open);
                $link.attr('aria-expanded', open ? 'true' : 'false');
                if (open) {
                    alignSubmenu($item);
                }
            }
        });

        $roots.on('keydown.tnNav', toggleSelector, function (e) {
            var $link = $(this);
            var $item = $link.parent('.tn-item');
            if (!$item.length) {
                $item = $link.closest('.tn-item');
            }
            if (e.key === 'Escape') {
                closeAll($item.parent());
                this.focus();
            }
            if (e.key === ' ' || e.key === 'Enter') {
                var href = $link.attr('href');
                if (!href || href === '#') {
                    e.preventDefault();
                    $link.trigger('click');
                }
            }
            if (e.key === 'ArrowDown' && $item.hasClass('tn-has-children')) {
                e.preventDefault();
                $item.addClass('is-open').children('.tn-link').attr('aria-expanded', 'true');
                $item.children('.tn-submenu').find('.tn-link').first().trigger('focus');
            }
        });

        $roots.on('mouseenter.tnNav focusin.tnNav', '.tn-item.tn-has-children', function () {
            if (isCompactNav()) {
                return;
            }
            $(this).children('.tn-link').attr('aria-expanded', 'true');
            alignSubmenu($(this));
        });
        $roots.on('mouseleave.tnNav', '.tn-item.tn-has-children', function () {
            if (isCompactNav()) {
                return;
            }
            if (!$(this).is(':focus-within')) {
                $(this).children('.tn-link').attr('aria-expanded', 'false');
            }
        });
        $roots.on('focusout.tnNav', '.tn-item.tn-has-children', function () {
            var $item = $(this);
            window.setTimeout(function () {
                if (!$item.is(':focus-within') && !$item.hasClass('is-open')) {
                    $item.children('.tn-link').attr('aria-expanded', 'false');
                }
            }, 0);
        });

        $(document).on('click.tnNav', function (e) {
            if ($(e.target).closest('#top-menu-nav, #top-menu-mobile-nav-slot, #top-menu-floating-bar, #top-menu-mobile-panel, #top-menu-hamburger').length) {
                return;
            }
            closeAll();
        });

        if (isCompactNav()) {
            closeAll();
        }

        $(window).off('resize.tnNav').on('resize.tnNav', function () {
            if (isCompactNav()) {
                closeAll();
            }
        });
    };

    module.initOnPjaxLoad = true;
    module.export({
        init: function () {
            initAdmin();
            initNav();
        }
    });
});

(function ($) {
    if (!$) {
        return;
    }
    $(document).off('click.tnGuide').on('click.tnGuide', '[data-tn-guide-toggle]', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var $btn = $(this);
        var id = $btn.attr('aria-controls');
        var panel = id ? document.getElementById(id) : $btn.nextAll('.tn-guide__panel').get(0);
        var $panel = $(panel);
        var open = $btn.attr('aria-expanded') !== 'true';
        $btn.attr('aria-expanded', open ? 'true' : 'false');
        $btn.toggleClass('is-open', open);
        if (!$panel.length) {
            return;
        }
        if (open) {
            $panel.removeAttr('hidden').prop('hidden', false);
        } else {
            $panel.attr('hidden', 'hidden').prop('hidden', true);
        }
    });
    $(document).off('click.tnAccAll').on('click.tnAccAll', '[data-tn-acc-all]', function (e) {
        e.preventDefault();
        var open = $(this).attr('data-tn-acc-all') === 'open';
        $(this).closest('[data-tn-admin]').find('details.tn-set-acc').prop('open', open);
    });
})(window.jQuery);

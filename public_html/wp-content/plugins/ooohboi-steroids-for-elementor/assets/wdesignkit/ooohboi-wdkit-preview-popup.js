(function ($) {
    const { __ } = wp.i18n;

    const ENABLE_TEMPLATES_TEXT = __("Enable Templates", "ooohboi-steroids");
    const INSTALLING_TEXT = __("Installing WDesignKit", "ooohboi-steroids");
    const WAITING_TEXT = __("Waiting...", "ooohboi-steroids");

    $("document").ready(function () {
        let templateAddSection = $("#tmpl-elementor-add-section");

        if (0 < templateAddSection.length) {
            var oldTemplateButton = templateAddSection.html();
                oldTemplateButton = oldTemplateButton.replace('<div class="elementor-add-section-drag-title', '<div data-mode="dark" class="elementor-add-section-area-button elementor-action-ob-wdkit-button" title="' + __("WDesignKit") + '"><a href="#" class="ob-wkit-main-logo-div"></a></div><div class="elementor-add-section-drag-title');
                templateAddSection.html(oldTemplateButton);
        }

        elementor.on("preview:loaded", function () {
            
            window.ob_wdkit_editor = elementorCommon.dialogsManager.createWidget(
                "lightbox",
                {
                    id: "ob-wdkit-elementorp",
                    headerMessage: !1,
                    message: "",
                    hide: {
                        auto: !1,
                        onClick: !1,
                        onOutsideClick: false,
                        onOutsideContextMenu: !1,
                        onBackgroundClick: !0,
                    },
                    position: {
                        my: "center",
                        at: "center",
                    },
                    onShow: function () {
                        var dialogLightboxContent = $(".dialog-lightbox-message"),
                            clonedWrapElement = $("#ob-wdkit-wrap");

                            clonedWrapElement = clonedWrapElement.clone(true).show()
                            dialogLightboxContent.html(clonedWrapElement);

                            dialogLightboxContent.on("click", ".ob-close-btn", function () {
                                window.ob_wdkit_editor.hide();
                            });
                    },
                    onHide: function () {
                        window.ob_wdkit_editor.destroy();
                    }
                }
            );

            $(elementor.$previewContents[0].body).on("click", ".elementor-action-ob-wdkit-button", function (event) {
                window.ob_wdkit_editor.show();
            });

            $(document).on('click', '.ob-not-show-again', function (e) {
                e.preventDefault();
                $.ajax({
                    url: Ooohboi_Wdkit_Preview_Popup.ajax_url,
                    dataType: 'json',
                    type: "POST",
                    async: true,
                    data: {
                        action: 'ob_dont_show_again',
                        security: Ooohboi_Wdkit_Preview_Popup.nonce
                    },
                    success: function (res) {
                        elementor.saver.update.apply().then(function () {
                            window.location.reload();
                        });
                    },
                    error: function (xhr, status, error) {
                        console.log('Response:', xhr.responseText);
                    }
                });
            });

            $(document).on('click', '.ob-wdesign-install', function (e) {
                e.preventDefault();

                var $button = $(this);
                var $loader = $button.find('.ob-wb-loader-circle');
                var $text = $button.find('.ob-enable-text');

                if ($text.length > 0) {
                    $text.text(INSTALLING_TEXT);
                } else {
                    var $ob_visitPlugin = $button.find('.ob-visit-plugin');
                    if ($ob_visitPlugin.length > 0) {
                        $ob_visitPlugin.text(WAITING_TEXT);
                    }
                }

                $loader.css('display', 'block');

                jQuery.ajax({
                    url: Ooohboi_Wdkit_Preview_Popup.ajax_url,
                    dataType: 'json',
                    type: "post",
                    async: true,
                    data: {
                        action: 'ob_install_wdkit',
                        security: Ooohboi_Wdkit_Preview_Popup.nonce,
                    },
                    success: function (res) {

                        $loader.css('display', 'none');

                        if (true === res.success) {
                            elementor.saver.update.apply().then(function () {
                                window.location.reload();
                            });

                        } else {
                            $text.text(ENABLE_TEMPLATES_TEXT);
                        }
                    },
                    error: function () {
                        $loader.css('display', 'none');
                        $text.css('display', 'block').text(ENABLE_TEMPLATES_TEXT);
                    }
                });
            });
        });
    });
})(jQuery);
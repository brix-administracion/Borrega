'use strict';

( function ( $, w ) {
    
    var $window = $( w ); 

	function debounce(func, wait, immediate) {
		var timeout;
		return function() {
			var context = this, args = arguments;
			var later = function() {
				timeout = null;
				if (!immediate) func.apply(context, args);
			};
			var callNow = immediate && !timeout;
			clearTimeout(timeout);
			timeout = setTimeout(later, wait);
			if (callNow) func.apply(context, args);
		};
	}

    // Wait up to ~3s for Elementor's async wrapper; if it never appears, load our own Swiper (global)
    function obInitSwiper(node, config, done) {
        if (!node) return;
        if (node.__obGliderInited && node.__obSwiperInstance) { done && done(node.__obSwiperInstance); return; }

        var tries = 0, maxTries = 30; // 30 * 100ms
        (function waitWrapper() {
            tries++;
            if (window.elementorFrontend && elementorFrontend.utils && elementorFrontend.utils.swiper) {
                const AsyncSwiper = elementorFrontend.utils.swiper;
                new AsyncSwiper(node, config).then(function (inst) {
                    node.__obSwiperInstance = inst;
                    node.__obGliderInited = true;
                    done && done(inst);
                });
                return;
            }
            if (tries < maxTries) return setTimeout(waitWrapper, 100);

            // Wrapper never showed -> fallback to global
            obEnsureGlobalSwiper(function () {
                if (window.Swiper) {
                    var inst = new window.Swiper(node, config);
                    node.__obSwiperInstance = inst;
                    node.__obGliderInited = true;
                    done && done(inst);
                } else {
                    console.error('[Glider] Global Swiper still unavailable.');
                }
            });
        })();
    }

    function obEnsureGlobalSwiper(cb) {
        if (window.Swiper) return cb && cb();

        var url = (window.ooohboiGlider && window.ooohboiGlider.swiperJsUrl) ? window.ooohboiGlider.swiperJsUrl : null;
        if (!url) { console.error('[Glider] Missing ooohboiGlider.swiperJsUrl'); return cb && cb(); }

        // avoid double inject
        if (document.querySelector('script[data-ob="swiper-fallback"]')) {
            var t = 0, iv = setInterval(function () {
                if (window.Swiper || t++ > 30) { clearInterval(iv); cb && cb(); }
            }, 100);
            return;
        }

        var s = document.createElement('script');
        s.src = url;
        s.async = true;
        s.defer = true;
        s.setAttribute('data-ob', 'swiper-fallback');
        s.onload = function () { cb && cb(); };
        s.onerror = function () { console.error('[Glider] Failed to load fallback Swiper JS:', url); cb && cb(); };
        document.head.appendChild(s);
    }


    $window.on( 'elementor/frontend/init', function() {

        var ModuleHandler = elementorModules.frontend.handlers.Base, 
        Glider; 

        Glider = ModuleHandler.extend( {

            me_the_swiper: 'undefined', 
            glider_external_controls: [], 

            onInit: function() {
                ModuleHandler.prototype.onInit.apply( this, arguments );
                if( this.isGlider() && this.isGliderCandidate() ) {

                    this.$element.addClass( 'ob-is-glider' ); 
                    this.generateSwiperStructure(); 

                } else if( this.isGlider() && this.isOldGliderCandidate() ) { // if not a container!!!

                    this.$element.addClass( 'ob-is-glider' ); 
                    this.generateSwiperOld(); 

                }
            },

            isGlider: function() {
                return ( this.getElementSettings( '_ob_glider_is_slider' ) === 'yes' );
            }, 

            isGliderCandidate: function() {
                return ( 
                    // //   !this.$element.closest('.swiper').length && // not itself inside some other swiper
                    // (
                    //  this.$element.find( '.e-con-inner' ).children( '[data-element_type="container"]' ).length > 1 || this.$element.children( '[data-element_type="container"]' ).length ) );

                    ! this.$element.closest( '.swiper' ).length && 
                    ! this.$element.find( '.swiper' ).length && 
                    ( this.$element.find( '.e-con-inner' ).children( '[data-element_type="container"]' ).length > 1 || this.$element.children( '[data-element_type="container"]' ).length ) );
            }, 

            isOldGliderCandidate: function() { // it's not a container 
                return ( 'section' === this.$element.attr( 'data-element_type' ) );
            }, 

            onElementChange: function( changedProp ) {

                if( changedProp === '_ob_glider_is_slider' ) { 
                //     return;
                // } 

                console.log('[Glider] onElementChange ->', changedProp, 'isCandidate?', this.isGliderCandidate() );

                    if( this.isGliderCandidate() ) {

                        if( 'yes' === this.getElementSettings( '_ob_glider_is_slider' ) ) {
                            this.$element.attr( 'id', 'glider-' + this.getID() ); 
                            this.$element.addClass( 'ob-is-glider' ); 
                            this.generateSwiperStructure();
                        } else if( '' === this.getElementSettings( '_ob_glider_is_slider' ) ) {
                            this.$element.removeClass( 'ob-is-glider' ); 
                            elementor.reloadPreview();
                        } 

                    } else if( this.isOldGliderCandidate() ) { // if not a container  !!!

                        if( 'yes' === this.getElementSettings( '_ob_glider_is_slider' ) ) {
                            this.$element.attr( 'id', 'glider-' + this.getID() ); 
                            this.$element.addClass( 'ob-is-glider' ); 
                            this.generateSwiperOld();
                        } else if( '' === this.getElementSettings( '_ob_glider_is_slider' ) ) {
                            this.$element.removeClass( 'ob-is-glider' ); 
                            elementor.reloadPreview();
                        } 

                    }

                }
            }, 

            generateSwiperStructure: function() {

                if( this.$element.find( '.ob-swiper-bundle' ).length ) return; // bail if the wraping element exists

                var is_boxed = this.$element.hasClass( 'e-con-boxed' ) ? true : false;

                if( ! is_boxed ) this.$element.children( '[data-element_type="container"]' ).wrapAll( '<div class="ob-swiper-bundle swiper"></div>' ); 
                else this.$element.find( '.e-con-inner' ).children( '[data-element_type="container"]' ).wrapAll( '<div class="ob-swiper-bundle swiper"></div>' ); 

                var wrapr = this.$element.find( '.ob-swiper-bundle' );

                wrapr.children( '[data-element_type="container"]' ).addClass( 'swiper-slide' ).wrapAll( '<div class="swiper-wrapper"></div>' );

                // grab the settings...
                // controls
                var settingz = {};

                settingz.icon_obj = this.getElementSettings( '_ob_glider_nav_icon' );
                var default_next = $( '<div class="swiper-button-next"></div>' );
                var deafult_prev = $( '<div class="swiper-button-prev"></div>' );
                var default_pagi = $( '<div class="swiper-pagination"></div>' );

                // append controls: next prev pagination
                wrapr
                .append( default_next )
                .append( deafult_prev )
                .append( default_pagi );

                // SCOPED DOM nodes (must exist before config)
                var nextEl = wrapr.find('.swiper-button-next')[0];
                var prevEl = wrapr.find('.swiper-button-prev')[0];
                var pagEl = wrapr.find('.swiper-pagination')[0];

                if( ! $.isEmptyObject( settingz.icon_obj ) ) {

                    if( 'string' === typeof settingz.icon_obj.value ) {

                        default_next.html( '<i aria-hidden="true" class="' + settingz.icon_obj.value + '"></i>' );
                        deafult_prev.html( '<i aria-hidden="true" class="' + settingz.icon_obj.value + ' fa-flip-horizontal"></i>' );

                    } else {

                        $.get( settingz.icon_obj.value, null, function( data ) {
                            default_next.html( document.adoptNode( $( 'svg', data )[ 0 ] ) );
                        }, 'xml' );
                        $.get( settingz.icon_obj.value, null, function( data ) {
                            deafult_prev.html( document.adoptNode( $( 'svg', data )[ 0 ] ) );
                        }, 'xml' );

                    }

                }

                // settings (unchanged)
                settingz.pagination_type = this.getElementSettings( '_ob_glider_pagination_type' ) || 'bullets';
                settingz.allowTouchMove = this.getElementSettings( '_ob_glider_allow_touch_move' );
                settingz.autoheight = this.getElementSettings( '_ob_glider_auto_h' ) || 'no';
                settingz.effect = this.getElementSettings( '_ob_glider_effect' ) || 'slide';
                settingz.loop = this.getElementSettings( '_ob_glider_loop' ) || false;
                settingz.direction = this.getElementSettings( '_ob_glider_direction' ) || 'horizontal';
                settingz.parallax = this.getElementSettings( '_ob_glider_parallax' );
                settingz.speed = this.getElementSettings( '_ob_glider_speed' ) || 450;
                var autoplayed = this.getElementSettings( '_ob_glider_autoplay' ) || false;
                if( autoplayed ) {
                    settingz.autoplay = {
                        'delay': this.getElementSettings( '_ob_glider_autoplay_delay' ), 
                    }
                } else settingz.autoplay = false;
                settingz.mousewheel = this.getElementSettings( '_ob_glider_allow_mousewheel' );

                settingz.allowMultiSlides = this.getElementSettings( '_ob_glider_allow_multi_slides' );
                var breakpointsSettings = {},
                breakpoints = elementorFrontend.config.breakpoints;

                // widescreen
                if( undefined !== this.getElementSettings( '_ob_glider_slides_per_view_widescreen' ) ) {
                    breakpointsSettings[breakpoints.xxl] = {
                        slidesPerView: parseInt( this.getElementSettings( '_ob_glider_slides_per_view_widescreen' ) ) || 1,
                        slidesPerGroup: parseInt( this.getElementSettings( '_ob_glider_slides_to_scroll_widescreen' ) ) || 1,
                        spaceBetween: parseInt( this.getElementSettings( '_ob_glider_space_between_widescreen' ) ) || 0,
                    }; 
                }
                // desktop - default
                breakpointsSettings[breakpoints.xl] = {
                    slidesPerView: parseInt( this.getElementSettings( '_ob_glider_slides_per_view' ) ) || 1, 
                    slidesPerGroup: parseInt( this.getElementSettings( '_ob_glider_slides_to_scroll' ) ) || 1, 
                    spaceBetween: parseInt( this.getElementSettings( '_ob_glider_space_between' ) ) || 0, 
                };
                // tablet
                breakpointsSettings[breakpoints.md] = {
                    slidesPerView: parseInt( this.getElementSettings( '_ob_glider_slides_per_view_tablet' ) ) || 1,
                    slidesPerGroup: parseInt( this.getElementSettings( '_ob_glider_slides_to_scroll_tablet' ) ) || 1,
                    spaceBetween: parseInt( this.getElementSettings( '_ob_glider_space_between_tablet' ) ) || 0,
                }; 
                // mobile
                breakpointsSettings[breakpoints.sm] = {
                    slidesPerView: parseInt( this.getElementSettings( '_ob_glider_slides_per_view_mobile' ) ) || 1,
                    slidesPerGroup: parseInt( this.getElementSettings( '_ob_glider_slides_to_scroll_mobile' ) ) || 1,
                    spaceBetween: parseInt( this.getElementSettings( '_ob_glider_space_between_mobile' ) ) || 0,
                }; 

                if( 'fade' === settingz.effect || 'yes' !== settingz.allowMultiSlides ) settingz.breakpoints = {}; // no breakpoints needed in this case
                else settingz.breakpoints = breakpointsSettings; 

                // centered slides - v1.7.9
                settingz.slides_centered = this.getElementSettings( '_ob_glider_centered_slides' ); 
                settingz.slides_centered_bounds = this.getElementSettings( '_ob_glider_centered_bounds_slides' ); 
                settingz.slides_round_lenghts = this.getElementSettings( '_ob_glider_roundlengths_slides' ); 

                // ✅ Build config AFTER nodes exist, include nav/pagination inside
                // create swiper ----------------------------------------------------------------------------------
                var swiper_config = {
                    allowTouchMove: ( 'yes' === settingz.allowTouchMove ? true : false ), 
                    autoHeight: ( 'yes' === settingz.autoheight ? true : false ), 
                    effect: settingz.effect, 
                    loop: settingz.loop, 
                    direction: ( 'fade' === settingz.effect ? 'horizontal' : settingz.direction ), 
                    parallax: ( 'yes' === settingz.parallax ? true : false ),
                    speed: settingz.speed, 
                    slidesPerView: 1, 
                    slidesPerGroup: 1, 
                    spaceBetween: 0, 
                    breakpoints: settingz.breakpoints, 
                    centeredSlides: ( 'yes' === settingz.slides_centered ? true : false ), 
                    centeredSlidesBounds: ( 'yes' === settingz.slides_centered_bounds ? true : false ), 
                    roundLengths: ( 'yes' === settingz.slides_round_lenghts ? true : false ), 
                    navigation: {
                        nextEl: '.swiper-button-next',
                        prevEl: '.swiper-button-prev',
                    },
                    pagination: {
                        el: '.swiper-pagination', 
                        type: settingz.pagination_type, 
                        clickable: true, 
                    },
                    autoplay: settingz.autoplay, 
                    mousewheel: ( 'yes' === settingz.mousewheel ? true : false ), 
                    watchOverflow : true, /* gotta force it down */ 
                    on: {
                        init: function () {
                            wrapr.css( 'visibility', 'visible' );
                        },
                    },
                };
                // improved asset loading

                var node = wrapr[0], self = this;
                obInitSwiper(node, swiper_config, function (inst) {
                    self.me_the_swiper = inst;
                    self.runSyncStuff(inst);
                    requestAnimationFrame(function () { inst.update(); });
                });

                if( this.isEdit ) {
                    var TMP_this = this;
                    // the DOM hack to prevent all kinds of shit ... 
                    elementor.channels.editor.on( 'change:container', function( el ) {
                        if( el._parent && el._parent.model && el._parent.model.id === TMP_this.getID() ) {
                            w.dispatchEvent( new Event( 'resize' ) ); // trigger only if dealing with the Glider
                        }
                    } );
                }

            }, 

            generateSwiperOld: function() {

                var wrapr = this.$element.children( '.elementor-container' ).first();

                wrapr.addClass('ob-swiper-bundle swiper');

                wrapr.children( '[data-element_type="column"]' ).addClass( 'swiper-slide' ).wrapAll( '<div class="swiper-wrapper"></div>' );

                // grab the settings...
                var settingz = {};

                settingz.icon_obj = this.getElementSettings( '_ob_glider_nav_icon' );
                var default_next = $( '<div class="swiper-button-next"></div>' );
                var deafult_prev = $( '<div class="swiper-button-prev"></div>' );
                var default_pagi = $( '<div class="swiper-pagination"></div>' );

                // append controls: next prev pagination
                wrapr
                .append( default_next )
                .append( deafult_prev )
                .append( default_pagi );

                // Nodes first
                var nextEl = wrapr.find('.swiper-button-next')[0];
                var prevEl = wrapr.find('.swiper-button-prev')[0];
                var pagEl = wrapr.find('.swiper-pagination')[0];

                if( ! $.isEmptyObject( settingz.icon_obj ) ) {

                    if( 'string' === typeof settingz.icon_obj.value ) {

                        default_next.html( '<i aria-hidden="true" class="' + settingz.icon_obj.value + '"></i>' );
                        deafult_prev.html( '<i aria-hidden="true" class="' + settingz.icon_obj.value + ' fa-flip-horizontal"></i>' );

                    } else {

                        $.get( settingz.icon_obj.value, null, function( data ) {
                            default_next.html( document.adoptNode( $( 'svg', data )[ 0 ] ) );
                        }, 'xml' );
                        $.get( settingz.icon_obj.value, null, function( data ) {
                            deafult_prev.html( document.adoptNode( $( 'svg', data )[ 0 ] ) );
                        }, 'xml' );

                    }

                }
                
                // settings (same as container path)
                settingz.pagination_type = this.getElementSettings( '_ob_glider_pagination_type' ) || 'bullets';
                settingz.allowTouchMove = this.getElementSettings( '_ob_glider_allow_touch_move' );
                settingz.autoheight = this.getElementSettings( '_ob_glider_auto_h' ) || 'no';
                settingz.effect = this.getElementSettings( '_ob_glider_effect' ) || 'slide';
                settingz.loop = this.getElementSettings( '_ob_glider_loop' ) || false;
                settingz.direction = this.getElementSettings( '_ob_glider_direction' ) || 'horizontal';
                settingz.parallax = this.getElementSettings( '_ob_glider_parallax' );
                settingz.speed = this.getElementSettings( '_ob_glider_speed' ) || 450;
                var autoplayed = this.getElementSettings( '_ob_glider_autoplay' ) || false;
                if( autoplayed ) {
                    settingz.autoplay = {
                        'delay': this.getElementSettings( '_ob_glider_autoplay_delay' ), 
                    }
                } else settingz.autoplay = false;
                settingz.mousewheel = this.getElementSettings( '_ob_glider_allow_mousewheel' );

                settingz.allowMultiSlides = this.getElementSettings( '_ob_glider_allow_multi_slides' );
                var breakpointsSettings = {},
                breakpoints = elementorFrontend.config.breakpoints;

                // widescreen
                if( undefined !== this.getElementSettings( '_ob_glider_slides_per_view_widescreen' ) ) {
                    breakpointsSettings[breakpoints.xxl] = {
                        slidesPerView: parseInt( this.getElementSettings( '_ob_glider_slides_per_view_widescreen' ) ) || 1,
                        slidesPerGroup: parseInt( this.getElementSettings( '_ob_glider_slides_to_scroll_widescreen' ) ) || 1,
                        spaceBetween: parseInt( this.getElementSettings( '_ob_glider_space_between_widescreen' ) ) || 0,
                    }; 
                }
                // desktop - default
                breakpointsSettings[breakpoints.xl] = {
                    slidesPerView: parseInt( this.getElementSettings( '_ob_glider_slides_per_view' ) ) || 1, 
                    slidesPerGroup: parseInt( this.getElementSettings( '_ob_glider_slides_to_scroll' ) ) || 1, 
                    spaceBetween: parseInt( this.getElementSettings( '_ob_glider_space_between' ) ) || 0, 
                };
                // tablet
                breakpointsSettings[breakpoints.md] = {
                    slidesPerView: parseInt( this.getElementSettings( '_ob_glider_slides_per_view_tablet' ) ) || 1,
                    slidesPerGroup: parseInt( this.getElementSettings( '_ob_glider_slides_to_scroll_tablet' ) ) || 1,
                    spaceBetween: parseInt( this.getElementSettings( '_ob_glider_space_between_tablet' ) ) || 0,
                }; 
                // mobile
                breakpointsSettings[breakpoints.sm] = {
                    slidesPerView: parseInt( this.getElementSettings( '_ob_glider_slides_per_view_mobile' ) ) || 1,
                    slidesPerGroup: parseInt( this.getElementSettings( '_ob_glider_slides_to_scroll_mobile' ) ) || 1,
                    spaceBetween: parseInt( this.getElementSettings( '_ob_glider_space_between_mobile' ) ) || 0,
                }; 

                if( 'fade' === settingz.effect || 'yes' !== settingz.allowMultiSlides ) settingz.breakpoints = {}; // no breakpoints needed in this case
                else settingz.breakpoints = breakpointsSettings; 

                // centered slides - v1.7.9
                settingz.slides_centered = this.getElementSettings( '_ob_glider_centered_slides' ); 
                settingz.slides_centered_bounds = this.getElementSettings( '_ob_glider_centered_bounds_slides' ); 
                settingz.slides_round_lenghts = this.getElementSettings( '_ob_glider_roundlengths_slides' ); 

                // ✅ Build config here (includes nav/pagination)
                // create swiper ----------------------------------------------------------------------------------
                var swiper_config = {
                    allowTouchMove: ( 'yes' === settingz.allowTouchMove ? true : false ), 
                    autoHeight: ( 'yes' === settingz.autoheight ? true : false ), 
                    effect: settingz.effect, 
                    loop: settingz.loop, 
                    direction: ( 'fade' === settingz.effect ? 'horizontal' : settingz.direction ), 
                    parallax: ( 'yes' === settingz.parallax ? true : false ),
                    speed: settingz.speed, 
                    slidesPerView: 1, 
                    slidesPerGroup: 1, 
                    spaceBetween: 0, 
                    breakpoints: settingz.breakpoints, 
                    centeredSlides: ( 'yes' === settingz.slides_centered ? true : false ), 
                    centeredSlidesBounds: ( 'yes' === settingz.slides_centered_bounds ? true : false ), 
                    roundLengths: ( 'yes' === settingz.slides_round_lenghts ? true : false ), 
                    navigation: {
                        nextEl: '.swiper-button-next',
                        prevEl: '.swiper-button-prev',
                    },
                    pagination: {
                        el: '.swiper-pagination', 
                        type: settingz.pagination_type, 
                        clickable: true, 
                    },
                    autoplay: settingz.autoplay, 
                    mousewheel: ( 'yes' === settingz.mousewheel ? true : false ), 
                    watchOverflow : true, /* gotta force it down */ 
                    on: {
                        init: function () {
                            wrapr.css( 'visibility', 'visible' );
                        },
                    },
                };
                // improved asset loading

                var node = wrapr[0], self = this;
                obInitSwiper(node, swiper_config, function (inst) {
                    self.me_the_swiper = inst;
                    self.runSyncStuff(inst);
                    requestAnimationFrame(function () { inst.update(); });
                });

                if( this.isEdit ) {
                    var TMP_this = this;
                    // the DOM hack to prevent all kinds of shit ... 
                    elementor.channels.editor.on( 'change:section', function( el ) {
                        if( el._parent && el._parent.model && el._parent.model.id === TMP_this.getID() ) {
                            w.dispatchEvent( new Event( 'resize' ) ); // trigger only if dealing with the Glider
                        }
                    } );
                }

            }, 

            runSyncStuff: function( swiper_ob ) {

                // external control via the CSS class
                // : .glider-<id>-gotoslide-<num>
                this.glider_external_controls = $( 'body' ).find( '[class*="glider-' + this.$element[ 0 ].dataset[ 'id' ] + '-gotoslide-"]' ) || [];

                if( this.glider_external_controls.length ) {

                    this.glider_external_controls.each( function() {
                        this.target_swiper = swiper_ob;
                    } );

                    this.glider_external_controls.on( 'click', function( e ) {
                        var m = $(this).attr('class').match(/-gotoslide-(\d+)/);

                        var slide_num = m ? parseInt(m[1], 10) : -1;
                        if( slide_num >= 0 ) this.target_swiper.slideTo( slide_num );

                        e.preventDefault(); // bail
            
                    } );
                }

            }, 

        } );

        var handlersList = {

            'section': Glider, 
            'container': Glider, 

        };
        $.each( handlersList, function( widgetName, handlerClass ) {

            elementorFrontend.hooks.addAction( 'frontend/element_ready/' + widgetName, function( $scope ) {
                
                elementorFrontend.elementsHandler.addHandler( handlerClass, { $element: $scope } );

            } );

        } );

    } ); 


} ( jQuery, window ) );
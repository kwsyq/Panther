/*  js/addHoverHelp.js

    EXECUTIVE SUMMARY: A tool to allow easy adding of hover help / tooltips, by
    providing access to the JS function addHoverHelp, defined here. Hover help will be displayed
    for the specified duration (default: 5 seconds, expressed in milleseconds) or the
    mouse cursor leaves the element for which help is displayed.
    
    Hover help always waits until the cursor has lingered one second on the element before displaying.
    
    Unlike most of our files, this is pure JavaScript, no PHP.
    It should be referenced along the lines of <script src="PATH/js/addHoverHelp.js" />
    
    Then if (for example) you have an element with HTML ID 'foo', you can add hover help with 
    a call such as addHoverHelp($("#foo"), 'whatever text you want') or, to choose as specific 
    duration of display (in milleseconds) addHoverHelp($("#foo"), 'whatever text you want', duration). 
    
    Relies on jQuery already being included.    
*/

if (typeof addHoverHelp === 'undefined') {
    $('body').append('<div id="hover-help" style="display:none"></div>'); // part of the mechanism below, need only one of these in program
    // Add context-sensitive help to an element or elements and any associated label.
    // INPUT $element - jQuery element
    // INPUT text - hover text
    // INPUT duration - duration of display in milleseconds. Default is 5000 (5 seconds).
    function addHoverHelp($element, text, duration) {
        if (duration === undefined) {
            duration = 5000;
        }
            // Helper function
            function startContextSensitiveHelp(ev, $_this) {
                stopContextSensitiveHelp();
                let helpText = $_this.data('hoverHelp');
                if ($_this.prop('disabled')) {
                    helpText = 'Disabled';
                }
                let goLeft = false;
                {
                    const windowWidth = $('window').width();
                    if ( ev.pageX < windowWidth - 280) {
                        $('#hover-help').data('left', ev.pageX + 25);
                    } else {
                        $('#hover-help').data('left', ev.pageX + -280);
                        goLeft = true;
                    }
    
                    if ( ev.pageY > 50) {
                        $('#hover-help').data('top', ev.pageY - 50);
                    } else {
                        $('#hover-help').data('top', ev.pageY + 15);
                    }
    
                    addHoverHelp.autoClearContextSensitiveHelp = setTimeout( 
                        function() {
                            let maxZIndex = 0;
                            $('div').filter(':visible').each(function() {
                                const $this = $(this);
                                const z = parseInt($this.css('zIndex'), 10);
                                if (z) {
                                    maxZIndex = Math.max(maxZIndex, z);
                                }
                            });
                            $('#hover-help').html(helpText).show().offset({
                                left:$('#hover-help').data('left'),
                                top:Math.max(0, 
                                    Math.min(
                                        $('#hover-help').data('top'),
                                        $(window).height() - $('#hover-help').height()-5)
                                    )
                            }).css({
                                'zIndex': maxZIndex+1,
                                'border': '1px solid black',
                                'backgroundColor': 'yellow',
                                'position': 'absolute'
                            });
                            
                            if (goLeft && $('#hover-help').width() < 250) {
                                // It's narrow & it's to the left, bring it closer.
                                $('#hover-help').offset({
                                    left:$('#hover-help').offset().left + 250 - $('#hover-help').width(), 
                                    top:$('#hover-help').data('top')
                                });
                            }
                            addHoverHelp.autoClearContextSensitiveHelp = setTimeout(stopContextSensitiveHelp, duration); // never show it for more than specified duration
                        }, 
                        1000 // show it after one second of hover in element
                    );
                }    
            }
            // Helper function
            function stopContextSensitiveHelp() {
                clearTimeout(addHoverHelp.autoClearContextSensitiveHelp);
                addHoverHelp.autoClearContextSensitiveHelp = null;
                $('#hover-help').css('zIndex', '0').html('').hide();
            }

        if ($element && $element.length) { // no need to do $.each, because there is never more than one of these at a time.        
            const $label = $('label[for="' + $element.attr('id') + '"]'); 
            $element
                .add($label)
                .data('hoverHelp', text)
                .off('hoverHelp')
                .on('mouseenter.hoverHelp', function(event){
                    startContextSensitiveHelp(event, $element);
                })
                .on('mouseleave.hoverHelp mousedown.hoverHelp keypress.hoverHelp', stopContextSensitiveHelp);
        }
    } // END function addHoverHelp
    addHoverHelp.autoClearContextSensitiveHelp = null;
} // END if (typeof addHoverHelp === 'undefined')
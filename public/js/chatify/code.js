/**
 *-------------------------------------------------------------
 * Global variables
 *-------------------------------------------------------------
 */
 var messenger,
 auth_id = $('meta[name=url]').attr('data-user'),
 route = $('meta[name=route]').attr('content'),
 url = $('meta[name=url]').attr('content'),
 access_token = $('meta[name="csrf-token"]').attr('content'),
 typingTimeout,
 typingNow = 0,
 temporaryMsgId = 0,
 defaultAvatarInSettings = null,
 messengerColor,
 dark_mode;
 messengerTitleDefault = $('.messenger-headTitle').text(),
 messageInput = $('#message-form .m-send');


let messagesContainer = $('.messenger-messagingView .m-body');

/**
*-------------------------------------------------------------
* Global Templates
*-------------------------------------------------------------
*/
// Loading svg
function loadingSVG(w_h = '25px', className = null) {
 return `
 <svg class="loadingSVG ` + className + `" xmlns="http://www.w3.org/2000/svg" width="` + w_h + `" height="` + w_h + `" viewBox="0 0 40 40" stroke="#2196f3">
   <g fill="none" fill-rule="evenodd">
     <g transform="translate(2 2)" stroke-width="3">
       <circle stroke-opacity=".1" cx="18" cy="18" r="18"></circle>
       <path d="M36 18c0-9.94-8.06-18-18-18" transform="rotate(349.311 18 18)">
           <animateTransform attributeName="transform" type="rotate" from="0 18 18" to="360 18 18" dur=".8s" repeatCount="indefinite"></animateTransform>
       </path>
     </g>
   </g>
 </svg>
 `;
}

// loading placeholder for users list item
function listItemLoading(items) {
 let template = '';
 for (let i = 0; i < items; i++) {
     template += `
     <div class="loadingPlaceholder">
       <div class="loadingPlaceholder-wrapper">
         <div class="loadingPlaceholder-body">
         <table class="loadingPlaceholder-header">
           <tr>
             <td style="width: 45px;"><div class="loadingPlaceholder-avatar"></div></td>
             <td>
               <div class="loadingPlaceholder-name"></div>
                   <div class="loadingPlaceholder-date"></div>
             </td>
           </tr>
         </table>
         </div>
       </div>
   </div>
     `;
 }
 return template;
}


// loading placeholder for avatars
function avatarLoading(items) {
 let template = '';
 for (let i = 0; i < items; i++) {
     template += `
     <div class="loadingPlaceholder">
     <div class="loadingPlaceholder-wrapper">
         <div class="loadingPlaceholder-body">
             <table class="loadingPlaceholder-header">
                 <tr>
                     <td style="width: 45px;">
                         <div class="loadingPlaceholder-avatar" style="margin: 2px;"></div>
                     </td>
                 </tr>
             </table>
         </div>
     </div>
     </div>
     `;
 }
 return template;
}

// While sending a message, show this temporary message card.
function sendigCard(message, id) {
 return `
 <div class="message-card mc-sender" data-id="` + id + `">
     <p>` + message + `<sub><span class="far fa-clock"></span></sub></p>
 </div>
 `;
}

// upload image preview card.
function attachmentTemplate(fileType, fileName, imgURL = null) {
 if (fileType != 'image') {
     return `
     <div class="attachment-preview">
         <span class="fas fa-times cancel"></span>
         <p style="padding:0px 30px;"><span class="fas fa-file"></span> ` + fileName + `</p>
     </div>
     `;
 } else {
     return `
     <div class="attachment-preview">
         <span class="fas fa-times cancel"></span>
         <div class="image-file chat-image" style="background-image: url('` + imgURL + `');"></div>
         <p><span class="fas fa-file-image"></span> ` + fileName + `</p>
     </div>
     `;
 }
}

// Active Status Circle
function activeStatusCircle() {
 return `<span class="activeStatus"></span>`;
}

/**
*-------------------------------------------------------------
* Css Media Queries [For responsive design]
*-------------------------------------------------------------
*/
$(window).resize(function () {
 cssMediaQueries();
});

// Subscribe to Pusher private-chatify to update unseen counter in real time
try {
    if (typeof pusher !== 'undefined') {
        if (!window.__chatify_global_channel) {
            window.__chatify_global_channel = pusher.subscribe('private-chatify');
            window.__chatify_global_channel.bind('messaging', function (data) {
                try {
//                    console.debug && console.debug('[chatify][pusher] messaging event', data);
                    // only handle messages intended for this user
                    if (!data || (typeof data.to_id === 'undefined')) return;
                    // data.to_id is the recipient (user id) for server push
                    if (String(data.to_id) !== String(auth_id)) return;

                    // determine if the conversation is currently open
                    var openType = (typeof messenger === 'string' && messenger.indexOf('_') !== -1) ? messenger.split('_')[0] : null;
                    var openId = (typeof messenger === 'string' && messenger.indexOf('_') !== -1) ? messenger.split('_')[1] : null;

                    var isSameConversation = false;
                    if (data.type == 'user') {
                        // for user messages, data.from_id is the sender
                        if (openType == 'user' && String(openId) === String(data.from_id)) isSameConversation = true;
                    } else if (data.type == 'group') {
                        // for group messages, data.id is the project/group id
                        if (openType == 'group' && String(openId) === String(data.id)) isSameConversation = true;
                    }

                    if (!isSameConversation) {
                        // increment all matching counters
                        try {
                            var els = document.querySelectorAll('.custom_messanger_counter');
                            els.forEach(function (el) {
                                try {
                                    var raw = (el.textContent || '').trim();
                                    var digits = raw.match(/\d+/);
                                    var current = digits ? parseInt(digits[0], 10) : 0;
                                    var updated = false;
                                    for (var i = 0; i < el.childNodes.length; i++) {
                                        var node = el.childNodes[i];
                                        if (node.nodeType === Node.TEXT_NODE) {
                                            node.nodeValue = String(current + 1);
                                            updated = true;
                                            break;
                                        }
                                    }
                                    if (!updated) el.textContent = String(current + 1);
                                } catch (e) {
                                    console.debug && console.debug('[chatify] failed incrementing counter from pusher', e);
                                }
                            });
                        } catch (e) {
                            console.debug && console.debug('[chatify] pusher counter update failed', e);
                        }
                        // show toaster alert with mail icon for 10s
                        try {
                            if (typeof showChatifyToast === 'function') {
                                var toastTitle = 'New Message';
                                var toastMsg = '';
                                if (data.type === 'group' && data.group_name) {
                                    toastMsg = 'from ' + (data.sender_name || 'User') + ' in ' + data.group_name;
                                } else if (data.type === 'user' && data.sender_name) {
                                    toastMsg = 'from ' + data.sender_name;
                                } else {
                                    toastMsg = 'You have a new message';
                                }
                                showChatifyToast(toastMsg, 20000, toastTitle);
                            }
                        } catch (e) {
                            console.debug && console.debug('[chatify] showChatifyToast failed', e);
                        }
                        // play optional sound
                        try {
                            var audio = new Audio('/sounds/iphone_message_tone.mp3');
                            audio.play();
                        } catch (e) {
                            console.debug && console.debug('[chatify] pusher audio play failed', e);
                        }
                    } else {
                        console.debug && console.debug('[chatify] message belongs to open conversation, not incrementing counter');
                    }
                } catch (e) {
                    console.debug && console.debug('[chatify] messaging handler error', e);
                }
            });
        }
    } else {
        console.debug && console.debug('[chatify] pusher is not defined on this page');
    }
} catch (e) {
    console.debug && console.debug('[chatify] failed setting up pusher subscription', e);
}

// Helper: polished toast notification with mail icon (larger, animated)
function showChatifyToast(text, timeoutMs = 20000, title = '') {
    try {
        // inject styles once
        if (!document.getElementById('chatify-toast-styles')) {
            var style = document.createElement('style');
            style.id = 'chatify-toast-styles';
            style.innerHTML = `
                #chatify-toast-container { position: fixed; top: 20px; right: 20px; z-index: 99999; display:flex; flex-direction:column; gap:12px; align-items:flex-end; }
                .chatify-toast { display:flex; gap:14px; align-items:center; min-width:300px; max-width:480px; padding:14px 16px; border-radius:14px; color:#51459d; background: linear-gradient(135deg, rgba(255,255,255,0.03), rgba(255,255,255,0.01)); box-shadow: 0 14px 40px rgba(0,0,0,0.45); backdrop-filter: blur(6px); border:1px solid rgba(255,255,255,0.04); font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial; transform: translateX(18px); opacity:0; transition: transform .28s cubic-bezier(.2,.9,.2,1), opacity .28s ease; }
                .chatify-toast.show { transform: translateX(0); opacity:1; }
                .chatify-toast.hide { transform: translateX(18px); opacity:0; }
                .chatify-toast .chatify-toast-icon { flex:0 0 56px; height:56px; width:56px; border-radius:12px; display:flex; align-items:center; justify-content:center; background: linear-gradient(135deg,#6ec1ff,#5b8cff); box-shadow: inset 0 -6px 12px rgba(0,0,0,0.08); }
                .chatify-toast .chatify-toast-icon svg { width:26px; height:26px; fill:#fff; }
                .chatify-toast .chatify-toast-content { flex:1 1 auto; display:flex; flex-direction:column; }
                .chatify-toast .chatify-toast-title { font-weight:700; font-size:15px; margin-bottom:4px; }
                .chatify-toast .chatify-toast-message { font-size:14px; opacity:0.95; line-height:1.2;  }
                .chatify-toast .chatify-toast-close { background:transparent; border:0; color:rgba(255,255,255,0.75); font-size:18px; cursor:pointer; padding:6px; border-radius:8px; }
                .chatify-toast .chatify-toast-close:hover { background: rgba(255,255,255,0.03); }
            `;
            document.head.appendChild(style);
        }

        var container = document.getElementById('chatify-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'chatify-toast-container';
            document.body.appendChild(container);
        }

        var toast = document.createElement('div');
        toast.className = 'chatify-toast';

        var iconWrap = document.createElement('div');
        iconWrap.className = 'chatify-toast-icon';
        iconWrap.innerHTML = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"></path></svg>';

        var content = document.createElement('div');
        content.className = 'chatify-toast-content';
        if (title && title.length) {
            var t = document.createElement('div');
            t.className = 'chatify-toast-title';
            t.textContent = title;
            content.appendChild(t);
        }
        var msg = document.createElement('div');
        msg.className = 'chatify-toast-message';
        msg.textContent = text;
        content.appendChild(msg);

        var closeBtn = document.createElement('button');
        closeBtn.className = 'chatify-toast-close';
        closeBtn.setAttribute('aria-label', 'Close');
        closeBtn.innerHTML = '&times;';
        closeBtn.onclick = function () { hide(); };

        toast.appendChild(iconWrap);
        toast.appendChild(content);
        toast.appendChild(closeBtn);
        container.appendChild(toast);

        // show
        void toast.offsetWidth;
        toast.classList.add('show');

        function hide() {
            toast.classList.remove('show');
            toast.classList.add('hide');
            setTimeout(function () { try { if (toast && toast.parentNode) toast.parentNode.removeChild(toast); } catch (e) {} }, 380);
        }

        // auto remove
        setTimeout(function () { try { hide(); } catch (e) {} }, timeoutMs);

    } catch (e) {
        // keep logging commented as requested
        // console.debug && console.debug('[chatify] showChatifyToast error', e);
    }
}


function cssMediaQueries() {
 if (window.matchMedia('(min-width: 980px)').matches) {
     $('.messenger-listView').removeAttr('style');
 }
 if (window.matchMedia('(max-width: 980px)').matches) {
     $('body').find('.messenger-list-item').find('tr[data-action]').attr('data-action', '1');
     $('body').find('.favorite-list-item').find('div').attr('data-action', '1');
 } else {
     $('body').find('.messenger-list-item').find('tr[data-action]').attr('data-action', '0');
     $('body').find('.favorite-list-item').find('div').attr('data-action', '0');
 }
}


/**
*-------------------------------------------------------------
* App Modal
*-------------------------------------------------------------
*/
var app_modal = function ({
                           show = true,
                           name,
                           data = 0,
                           buttons = true,
                           header = null,
                           body = null,
                       }) {
 const modal = $('.app-modal[data-name=' + name + ']');
 // header
 header ? modal.find('.app-modal-header').html(header) : '';

 // body
 body ? modal.find('.app-modal-body').html(body) : '';

 // buttons
 buttons == true
     ? modal.find('.app-modal-footer').show()
     : modal.find('.app-modal-footer').hide();

 // show / hide
 if (show == true) {
     modal.show();
     $('.app-modal-card[data-name=' + name + ']').addClass('app-show-modal');
     $('.app-modal-card[data-name=' + name + ']').attr('data-modal', data);
 } else {
     modal.hide();
     $('.app-modal-card[data-name=' + name + ']').removeClass('app-show-modal');
     $('.app-modal-card[data-name=' + name + ']').attr('data-modal', data);
 }

};


/**
*-------------------------------------------------------------
* Slide to bottom on [action] - e.g. [message received, sent, loaded]
*-------------------------------------------------------------
*/
function scrollBottom(container) {
 if ($(container)[0] != "" && $(container)[0] && null && $(container)[0] != "undefined") {
     $(container).stop().animate({
         scrollTop: $(container)[0].scrollHeight
     });
 }

}

/**
*-------------------------------------------------------------
* click and drag to scroll - function
*-------------------------------------------------------------
*/
function hScroller(scroller) {
 const slider = document.querySelector(scroller);
 let isDown = false;
 let startX;
 let scrollLeft;

 if (slider != null && slider != "") {
     slider.addEventListener('mousedown', (e) => {
         isDown = true;
         startX = e.pageX - slider.offsetLeft;
         scrollLeft = slider.scrollLeft;
     });
     slider.addEventListener('mouseleave', () => {
         isDown = false;
     });
     slider.addEventListener('mouseup', () => {
         isDown = false;
     });
     slider.addEventListener('mousemove', (e) => {
         if (!isDown) return;
         e.preventDefault();
         const x = e.pageX - slider.offsetLeft;
         const walk = (x - startX) * 1;
         slider.scrollLeft = scrollLeft - walk;
     });
 }

}

/**
*-------------------------------------------------------------
* Disable/enable message form fields, messaging container...
* on load info or if needed elsewhere.
*
* Default : true
*-------------------------------------------------------------
*/
function disableOnLoad(action = true) {
 if (action == true) {
     // hide star button
     $('.add-to-favorite').hide();
     // hide send card
     $('.messenger-sendCard').hide();
     // add loading opacity to messages container
     messagesContainer.css('opacity', '.5');
     // disable message form fields
     messageInput.attr('readonly', 'readonly');
     $('#message-form button').attr('disabled', 'disabled');
     $('.upload-attachment').attr('disabled', 'disabled');
 } else {
     // show star button
     if (messenger.split('_')[1] != auth_id) {
         $('.add-to-favorite').show();
     }
     // show send card
     $('.messenger-sendCard').show();
     // remove loading opacity to messages container
     messagesContainer.css('opacity', '1');
     // enable message form fields
     messageInput.removeAttr('readonly');
     $('#message-form button').removeAttr('disabled');
     $('.upload-attachment').removeAttr('disabled');
 }
}

/**
*-------------------------------------------------------------
* Error message card
*-------------------------------------------------------------
*/
function errorMessageCard(id) {
 messagesContainer.find('.message-card[data-id=' + id + ']').addClass('mc-error');
 messagesContainer.find('.message-card[data-id=' + id + ']').find('svg.loadingSVG').remove();
 messagesContainer.find('.message-card[data-id=' + id + '] p').prepend('<span class="fas fa-exclamation-triangle"></span>');
}

/**
*-------------------------------------------------------------
* Fetch id data (user/group) and update the view
*-------------------------------------------------------------
*/
function IDinfo(id, type) {
 // clear temporary message id
 temporaryMsgId = 0;
 // clear typing now
 typingNow = 0;
 // show loading bar
 // NProgress.start();
 // disable mess
 // age form
 disableOnLoad();
 if (messenger != 0) {
     // get shared photos
     getSharedPhotos(id);
     // Get info
     $.ajax({
         url: url + '/idInfo',
         method: 'POST',
         data: {'_token': access_token, 'id': id, 'type': type},
         dataType: 'JSON',
         success: (data) => {
             // avatar photo
             $('.messenger-infoView').find('.avatar').css('background-image', 'url("' + data.user_avatar + '")');
             $('.header-avatar').css('background-image', 'url("' + data.user_avatar + '")');
             // Show shared and actions
             $('.messenger-infoView-btns .delete-conversation').show();
             $('.messenger-infoView-shared').show();
             // fetch messages
             fetchMessages(id, type);
             // focus on messaging input
             messageInput.focus();
             // update info in view
             $('.messenger-infoView .info-name').html(data.fetch.name);
             $('.m-header-messaging .user-name').html(data.fetch.name);
             // Star status
             data.favorite > 0
                 ? $('.add-to-favorite').addClass('favorite')
                 : $('.add-to-favorite').removeClass('favorite');
             // form reset and focus
             $("#message-form").trigger("reset");
             cancelAttachment();
             messageInput.focus();
         },
         error: () => {
             console.error('Error, check server response!');
             // remove loading bar
             // NProgress.done();
             // NProgress.remove();
         }
     });
 } else {
     // remove loading bar
     // NProgress.done();
     // NProgress.remove();
 }
}

/**
*-------------------------------------------------------------
* Send message function
*-------------------------------------------------------------
*/
function sendMessage() {
 temporaryMsgId += 1;
 let tempID = 'temp_' + temporaryMsgId
 let hasFile = $('.upload-attachment').val() ? true : false;
 function sanitizeHTML(value) {
     // Replace <, >, ", ', and & characters with their HTML entity equivalents
     return value.replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/&/g, '&amp;');
   }
   var msg = messageInput.val();
   var msg = sanitizeHTML(msg);
   // Use sanitizedInput in your code instead of userInput
 if ($.trim(msg).length > 0 || hasFile) {
     const formData = new FormData($("#message-form")[0]);
     formData.append('id', messenger.split('_')[1]);
     formData.append('type', messenger.split('_')[0]);
     formData.append('temporaryMsgId', tempID);
     formData.append('_token', access_token);
     $.ajax({
         url: $("#message-form").attr('action'),
         method: 'POST',
         data: formData,
         dataType: 'JSON',
         processData: false,
         contentType: false,
         beforeSend: () => {
             // remove message hint
             $(".message-hint").remove();
             // append message
             hasFile
                 ? messagesContainer.find('.messages').append(sendigCard(msg + '\n' + loadingSVG('28px'), tempID))
                 : messagesContainer.find('.messages').append(sendigCard(msg, tempID));
             // scroll to bottom
             scrollBottom(messagesContainer);
             messageInput.css({'height': '42px'});
             // form reset and focus
             $("#message-form").trigger("reset");
             cancelAttachment();
             messageInput.focus();
         },
         success: (data) => {
             if (data.error > 0) {
                 // message card error status
                 errorMessageCard(tempID);
                 // console.error(data.error_msg);
             } else {
                 // update contact item
                updateContatctItem(messenger.split('_')[1], messenger.split('_')[0]);
                messagesContainer.find('.mc-sender[data-id="sending"]').remove();
                 // get message before the sending one [temporary]
                 try {
                     var $serverMsg = $(data.message);
                     var serverMsgId = $serverMsg.attr('data-id');
                     // if top-level element has no data-id, search for .message-card child
                     if (!serverMsgId) {
                         var $msgCard = $serverMsg.find('.message-card[data-id]');
                         if ($msgCard.length > 0) {
                             serverMsgId = $msgCard.attr('data-id');
                             $serverMsg = $serverMsg; // keep the wrapper to preserve structure
                         }
                     }
                     if (serverMsgId) {
                         // if not already present (pusher may have appended it), insert it
                         if (messagesContainer.find('.message-card[data-id="' + serverMsgId + '"]').length === 0) {
                             messagesContainer.find('.message-card[data-id=' + data.tempID + ']').before($serverMsg);
                             console.debug && console.debug('[chatify] AJAX inserted message', serverMsgId);
                         } else {
                             console.debug && console.debug('[chatify] AJAX skipped inserting duplicate message', serverMsgId);
                         }
                     } else {
                         // fallback: insert raw HTML if no data-id found
                         messagesContainer.find('.message-card[data-id=' + data.tempID + ']').before(data.message);
                         console.debug && console.debug('[chatify] AJAX inserted message without id (fallback)');
                     }
                 } catch (e) {
                     // fallback on parse error
                     messagesContainer.find('.message-card[data-id=' + data.tempID + ']').before(data.message);
                     console.error && console.error('[chatify] Failed parsing server message HTML, inserted raw HTML', e);
                 }
                 // delete the temporary one
                 messagesContainer.find('.message-card[data-id=' + data.tempID + ']').remove();
                 // scroll to bottom
                 scrollBottom(messagesContainer);
                // send contact item updates
                sendContactItemUpdates(true);
                // Increment global unseen counter and play message sound (robust)
                (function () {
                    // safer increment: handle nested elements and multiple counters
                    try {
                        var els = document.querySelectorAll('.custom_messanger_counter');
                        if (els.length === 0) {
                            console.debug && console.debug('[chatify] custom_messanger_counter not found');
                        }
                        els.forEach(function (el) {
                            try {
                                // extract the first integer found in the element's text
                                var raw = (el.textContent || '').trim();
                                var digits = raw.match(/\d+/);
                                var current = digits ? parseInt(digits[0], 10) : 0;
                                // attempt to update an existing text node if present
                                var updated = false;
                                for (var i = 0; i < el.childNodes.length; i++) {
                                    var node = el.childNodes[i];
                                    if (node.nodeType === Node.TEXT_NODE) {
                                        node.nodeValue = String(current + 1);
                                        updated = true;
                                        break;
                                    }
                                }
                                if (!updated) {
                                    // no direct text node - replace entire content
                                    el.textContent = String(current + 1);
                                }
                                console.debug && console.debug('[chatify] incremented custom_messanger_counter', current, '->', current + 1, el);
                            } catch (e) {
                                console.debug && console.debug('[chatify] failed updating one counter element', e);
                            }
                        });
                    } catch (e) {
                        console.debug && console.debug('[chatify] Failed incrementing counter', e);
                    }
                    // play iPhone message sound
                    try {
                        var audio = new Audio('/sounds/iphone_message_tone.mp3');
                        audio.play();
                    } catch (e) {
                        console.debug && console.debug('[chatify] Audio play failed', e);
                    }
                })();
             }
         },
         error: () => {
             // message card error status
             errorMessageCard(tempID);
             // error log
             // console.error('Failed sending the message! Please, check your server response');
         }
     });
 }
 return false;
}

/**
*-------------------------------------------------------------
* Fetch messages from database
*-------------------------------------------------------------
*/
function fetchMessages(id, type) {
 if (messenger != 0) {
     $.ajax({
         url: url + '/fetchMessages',
         method: 'POST',
         data: {'_token': access_token, 'id': id, 'type': type},
         dataType: 'JSON',
         success: (data) => {
             // Enable message form if messenger not = 0; means if data is valid
             if (messenger != 0) {
                 disableOnLoad(false);
             }
             messagesContainer.find('.messages').html(data.messages);
             // scroll to bottom
             scrollBottom(messagesContainer);
             // remove loading bar
             // NProgress.done();
             // NProgress.remove();

             // trigger seen event
             makeSeen(true);
         },
         error: () => {
             // remove loading bar
             // NProgress.done();
             // NProgress.remove();
             console.error('Failed to fetch messages! check your server response.');
         }
     });
 }
}

/**
*-------------------------------------------------------------
* Cancel file attached in the message.
*-------------------------------------------------------------
*/
function cancelAttachment() {
 $('.messenger-sendCard').find('.attachment-preview').remove();
 $('.upload-attachment').replaceWith($('.upload-attachment').val('').clone(true));
}

/**
*-------------------------------------------------------------
* Cancel updating avatar in settings
*-------------------------------------------------------------
*/
function cancelUpdatingAvatar() {
 $('.upload-avatar-preview').css('background-image', defaultAvatarInSettings);
 $('.upload-avatar').replaceWith($('.upload-avatar').val('').clone(true));
}


/**
*-------------------------------------------------------------
* Pusher channels and event listening..
*-------------------------------------------------------------
*/

// subscribe to the channel
var channel = pusher.subscribe('private-chatify');

// Listen to messages, and append if data received
channel.bind('messaging', function (data) {
    let open_id = messenger.split('_')[1];
    let open_type = messenger.split('_')[0];
    let is_match = false;

    if (data.type && data.id) {
        if (data.type == 'group') {
             if (open_type == 'group' && open_id == data.id) {
                 is_match = true;
             }
        } else {
             if (open_type == 'user' && data.from_id == open_id && data.to_id == auth_id) {
                 is_match = true;
             }
        }
    } else {
         if (data.from_id == open_id && data.to_id == auth_id) {
             is_match = true;
         }
    }

    if (is_match) {
        // remove message hint
        $(".message-hint").remove();
        // dedupe: parse incoming message HTML and check data-id
        try {
            var $incoming = $(data.message);
            var incomingId = $incoming.attr('data-id');
            if (incomingId) {
                // if message with same id already exists, skip append
                if (messagesContainer.find('.message-card[data-id="' + incomingId + '"]').length === 0) {
                    messagesContainer.find('.messages').append($incoming);
                    console.debug && console.debug('[chatify] Appended incoming message', incomingId);
                } else {
                    console.debug && console.debug('[chatify] Dedupe skipped incoming message', incomingId);
                }
            } else {
                // fallback: append if no data-id present
                messagesContainer.find('.messages').append($incoming);
                console.debug && console.debug('[chatify] Appended incoming message without id');
            }
        } catch (e) {
            // if parsing fails for any reason, append raw HTML as before
            messagesContainer.find('.messages').append(data.message);
            console.error && console.error('[chatify] Failed parsing incoming message, appended raw HTML', e);
        }
        // scroll to bottom
        scrollBottom(messagesContainer);
        // trigger seen event
        makeSeen(true);
        // remove unseen counter for the user from the contacts list
        $('.messenger-list-item[data-contact=' + open_id + ']').find('tr>td>b').remove();
    }
});

// listen to typing indicator
channel.bind('client-typing', function (data) {
 if (data.from_id == messenger.split('_')[1] && data.to_id == auth_id) {
     data.typing == true ? messagesContainer.find('.typing-indicator').show()
         : messagesContainer.find('.typing-indicator').hide();
 }
 // scroll to bottom
 scrollBottom(messagesContainer);
});

// listen to seen event
channel.bind('client-seen', function (data) {
 if (data.from_id == messenger.split('_')[1] && data.to_id == auth_id) {
     if (data.seen == true) {
         $('.message-time').find('.fa-check').before('<span class="fas fa-check-double seen"></span> ');
         $('.message-time').find('.fa-check').remove();
         // console.info('[seen] triggered!');
     } else {
         // console.error('[seen] event not triggered!');
     }
 }
});

// listen to contact item updates event
channel.bind('client-contactItem', function (data) {
    if (data.type && data.type == 'group') {
        if (data.updating) {
            if ($('.messenger-list-item[data-contact=group_' + data.update_for + ']').length > 0) {
                updateContatctItem(data.update_for, 'group');
            }
        }
    } else {
        if (data.update_for == auth_id) {
            data.updating == true ? updateContatctItem(data.update_to)
                : /*console.error('[Contact Item updates] Updating failed!')*/ '';
        }
    }
});

// -------------------------------------
// presence channel [User Active Status]
var activeStatusChannel = pusher.subscribe('presence-activeStatus');

// Joined
activeStatusChannel.bind('pusher:member_added', function (member) {
 setActiveStatus(1, member.id);
 $('.messenger-list-item[data-contact=' + member.id + ']').find('.activeStatus').remove();
 $('.messenger-list-item[data-contact=' + member.id + ']').find('.avatar').before(activeStatusCircle());
});

// Leaved
activeStatusChannel.bind('pusher:member_removed', function (member) {
 setActiveStatus(0, member.id);
 $('.messenger-list-item[data-contact=' + member.id + ']').find('.activeStatus').remove();
});

/**
*-------------------------------------------------------------
* Trigger typing event
*-------------------------------------------------------------
*/
function isTyping(status) {
 return channel.trigger('client-typing', {
     from_id: auth_id, // Me
     to_id: messenger.split('_')[1], // Messenger
     typing: status,
 });
}

/**
*-------------------------------------------------------------
* Trigger seen event
*-------------------------------------------------------------
*/
function makeSeen(status) {
 // remove unseen counter for the user from the contacts list
 let contactSelector = messenger.split('_')[0] == 'group' ? 'group_' + messenger.split('_')[1] : messenger.split('_')[1];
 $('.messenger-list-item[data-contact=' + contactSelector + ']').find('tr>td>b').remove();
 // seen
 $.ajax({
     url: url + '/makeSeen',
     method: 'POST',
     data: {'_token': access_token, 'id': messenger.split('_')[1]},
     dataType: 'JSON',
     success: (data) => {
         $('.custom_messanger_counter').text(data.messengerCount);
     }
 });
 return channel.trigger('client-seen', {
     from_id: auth_id, // Me
     to_id: messenger.split('_')[1], // Messenger
     seen: status,
 });
}

/**
*-------------------------------------------------------------
* Trigger contact item updates
*-------------------------------------------------------------
*/
function sendContactItemUpdates(status) {
 return channel.trigger('client-contactItem', {
     update_for: messenger.split('_')[1], // Messenger
     update_to: auth_id, // Me
     updating: status,
     type: messenger.split('_')[0],
 });
}

/**
*-------------------------------------------------------------
* Check internet connection using pusher states
*-------------------------------------------------------------
*/
function checkInternet(state, selector) {
 let net_errs = 0;
 const messengerTitle = $('.messenger-headTitle');
 switch (state) {
     case 'connected':
         if (net_errs < 1) {
             messengerTitle.text(messengerTitleDefault);
             selector.addClass('successBG-rgba');
             selector.find('span').hide();
             selector.slideDown('fast', function () {
                 selector.find('.ic-connected').show();
             });
             setTimeout(function () {
                 $('.internet-connection').slideUp('fast');
             }, 3000);
         }
         break;
     case 'connecting':
         messengerTitle.text($('.ic-connecting').text());
         selector.removeClass('successBG-rgba');
         selector.find('span').hide();
         selector.slideDown('fast', function () {
             selector.find('.ic-connecting').show();
         });
         net_errs = 1;
         break;
     // Not connected
     default:
         messengerTitle.text($('.ic-noInternet').text());
         selector.removeClass('successBG-rgba');
         selector.find('span').hide();
         selector.slideDown('fast', function () {
             selector.find('.ic-noInternet').show();
         });
         net_errs = 1;
         break;
 }
}

/**
*-------------------------------------------------------------
* Get contacts
*-------------------------------------------------------------
*/
function getContacts() {
 $('.listOfContacts').html(listItemLoading(4));
 // Handle initial state where messenger = "0"
 let messengerParts = messenger.includes('_') ? messenger.split('_') : [null, null];
// console.debug('[chatify] getContacts called with messenger:', messenger, 'parts:', messengerParts);
 $.ajax({
     url: url + '/getContacts',
     method: 'POST',
     data: {'_token': access_token, 'messenger_id': messengerParts[1], 'messenger_type': messengerParts[0]},
     dataType: 'JSON',
     success: (data) => {
//         console.debug('[chatify] getContacts success:', data);
         $('.listOfContacts').html('');
         $('.listOfContacts').html(data.contacts);

         $('.all_members').html('');
         $('.all_members').html(data.allUsers);
         // update data-action required with [responsive design]
         cssMediaQueries();
     },
     error: (xhr, status, error) => {
         console.error('[chatify] getContacts error:', status, error, xhr.responseText);
     }
 });
}

/**
*-------------------------------------------------------------
* Update contact item
*-------------------------------------------------------------
*/
function updateContatctItem(user_id, type = 'user') {
    if (user_id != auth_id || type == 'group') {
        let contactSelector = type == 'group' ? 'group_' + user_id : user_id;
        let listItem = $('body').find('.listOfContacts').find('.messenger-list-item[data-contact=' + contactSelector + ']');
        $.ajax({
            url: url + '/updateContacts',
            method: 'POST',
            data: {'_token': access_token, 'user_id': user_id, 'messenger_id': messenger.split('_')[1], 'type': type, 'messenger_type': messenger.split('_')[0]},
            dataType: 'JSON',
            success: (data) => {
                listItem.remove();
                $('.listOfContacts').prepend(data.contactItem);
                $('.custom_messanger_counter').text(data.messengerCount);
                // update data-action required with [responsive design]
                cssMediaQueries();
            },
            error: () => {
                console.error('Server error, check your response');
            }
        });
    }
}

/**
*-------------------------------------------------------------
* Star
*-------------------------------------------------------------
*/

function star(user_id) {
  if (messenger.split('_')[1] != auth_id) {
     $.ajax({
         url: url + '/star',
         method: 'POST',
         data: {'_token': access_token, 'user_id': user_id},
         dataType: 'JSON',
         success: (data) => {
             data.status > 0
                 ? $('.add-to-favorite').addClass('favorite')
                 : $('.add-to-favorite').removeClass('favorite');

         },
         error: () => {
             console.error('Server error, check your response');
         }
     });
 }
}

/**
*-------------------------------------------------------------
* Get favorite list
*-------------------------------------------------------------
*/
function getFavoritesList() {
 $('.messenger-favorites').html(avatarLoading(4));
 $.ajax({
     url: url + '/favorites',
     method: 'POST',
     data: {'_token': access_token},
     dataType: 'JSON',
     success: (data) => {
         $('.messenger-favorites').html('');
         $('.messenger-favorites').html(data.favorites);
         // update data-action required with [responsive design]
         cssMediaQueries();
     },
     error: () => {
         console.error('Server error, check your response');
     }
 });
}

/**
*-------------------------------------------------------------
* Get shared photos
*-------------------------------------------------------------
*/
function getSharedPhotos(user_id) {
 $.ajax({
     url: url + '/shared',
     method: 'POST',
     data: {'_token': access_token, 'user_id': user_id},
     dataType: 'JSON',
     success: (data) => {
         $('.shared-photos-list').html(data.shared);
     },
     error: () => {
         console.error('Server error, check your response');
     }
 });
}

/**
*-------------------------------------------------------------
* Search in messenger
*-------------------------------------------------------------
*/
function messengerSearch(input) {
 $.ajax({
     url: url + '/search',
     method: 'GET',
     data: {'_token': access_token, 'input': input},
     dataType: 'JSON',
     beforeSend: () => {
         $('.search-records').html(listItemLoading(4));
     },
     success: (data) => {
         $('.search-records').find('svg').remove();
         data.addData == 'append'
             ? $('.search-records').append(data.records)
             : $('.search-records').html(data.records);
         // update data-action required with [responsive design]
         cssMediaQueries();
     },
     error: () => {
         console.error('Server error, check your response');
     }
 });
}

/**
*-------------------------------------------------------------
* Delete Conversation
*-------------------------------------------------------------
*/
function deleteConversation(id) {
 $.ajax({
     url: url + '/deleteConversation',
     method: 'POST',
     data: {'_token': access_token, 'id': id},
     dataType: 'JSON',
     beforeSend: () => {
         // hide delete modal
         app_modal({
             show: false,
             name: 'delete',
         });
         // Show waiting alert modal
         app_modal({
             show: true,
             name: 'alert',
             buttons: false,
             body: loadingSVG('32px'),
         });
     },
     success: (data) => {
         // delete contact from the list
         $('.listOfContacts').find('.messenger-list-item[data-contact=' + id + ']').remove();
         // refresh info
         IDinfo(id, messenger.split('_')[0]);

         data.deleted ? '' : console.error('Error occured!');

         // Hide waiting alert modal
         app_modal({
             show: false,
             name: 'alert',
             buttons: true,
             body: '',
         });
     },
     error: () => {
         console.error('Server error, check your response');
     }
 });
}


function updateSettings() {
 const formData = new FormData($("#updateAvatar")[0]);
 if (messengerColor) {
     formData.append('messengerColor', messengerColor);
 }
 if (dark_mode) {
     formData.append('dark_mode', dark_mode);
 }
 $.ajax({
     url: url + '/updateSettings',
     method: 'POST',
     data: formData,
     dataType: 'JSON',
     processData: false,
     contentType: false,
     beforeSend: () => {
         // close settings modal
         app_modal({
             show: false,
             name: 'settings',
         });
         // Show waiting alert modal
         app_modal({
             show: true,
             name: 'alert',
             buttons: false,
             body: loadingSVG('32px'),
         });
     },
     success: (data) => {
         if (data.error) {
             // Show error message in alert modal
             app_modal({
                 show: true,
                 name: 'alert',
                 buttons: true,
                 body: data.msg,
             });
         } else {
             // Hide alert modal
             app_modal({
                 show: false,
                 name: 'alert',
                 buttons: true,
                 body: '',
             });

             // reload the page
             location.reload(true);
         }
     },
     error: () => {
         console.error('Server error, check your response');
     }
 });
}

/**
*-------------------------------------------------------------
* Set Active status
*-------------------------------------------------------------
*/
function setActiveStatus(status, user_id) {
 $.ajax({
     url: url + '/setActiveStatus',
     method: 'POST',
     data: {'_token': access_token, 'user_id': user_id, 'status': status},
     dataType: 'JSON',
     success: (data) => {
         // Nothing to do
     },
     error: () => {
         console.error('Server error, check your response');
     }
 });
}


/**
*-------------------------------------------------------------
* On DOM ready
*-------------------------------------------------------------
*/
$(document).ready(function () {
 // get contacts list
 getContacts();

 // get contacts list
 getFavoritesList();

 // Clear typing timeout
 clearTimeout(typingTimeout);

 // NProgress configurations
 // NProgress.configure({showSpinner: false, minimum: 0.7, speed: 500});

 // make message input autosize.
 // autosize($('.m-send'));

 // check if pusher has access to the channel [Internet status]
 pusher.connection.bind('state_change', function (states) {
     let selector = $('.internet-connection');
     checkInternet(states.current, selector);
     // listening for pusher:subscription_succeeded
     channel.bind('pusher:subscription_succeeded', function () {
         // On connection state change [Updating] and get [info & msgs]
         // Only call IDinfo if messenger has been set to a valid contact
         if (messenger !== "0" && messenger.includes('_')) {
             IDinfo(messenger.split('_')[1], messenger.split('_')[0]);
         }
     });
 });

 // tabs on click, show/hide...
 $('.messenger-listView-tabs a').on('click', function () {
     var dataView = $(this).attr('data-view');
     $('.messenger-listView-tabs a').removeClass('active-tab');
     $(this).addClass('active-tab');
     $('.messenger-tab').hide();
     $('.messenger-tab[data-view=' + dataView + ']').show();
 });

 // set item active on click
 $('body').on('click', '.messenger-list-item', function () {
     $('.messenger-list-item').removeClass('m-list-active');
     $(this).addClass('m-list-active');
 });

 // show info side button
 $('.messenger-infoView nav a , .show-infoSide').on('click', function () {
     $('.messenger-infoView').toggle();
 });

 // x button for info section to show the main button.
 $('.messenger-infoView nav a').on('click', function () {
     $('.show-infoSide').show();
 });

 // hide showing button for info section.
 $('.show-infoSide').on('click', function () {
     $(this).hide();
 });

 // make favorites card dragable on click to slide.
 hScroller('.messenger-favorites');

 // click action for list item [user/group]
 $('body').on('click', '.messenger-list-item', function () {
     if ($(this).find('tr[data-action]').attr('data-action') == "1") {
         $('.messenger-listView').hide();
     }
     messenger = $(this).find('p[data-id]').attr('data-id');
     IDinfo(messenger.split('_')[1], messenger.split('_')[0]);
 });

 // click action for favorite button
 $('body').on('click', '.favorite-list-item', function () {
     if ($(this).find('div').attr('data-action') == "1") {
         $('.messenger-listView').hide();
     }
     messenger = 'user_' + $(this).find('div.avatar').attr('data-id');
     IDinfo(messenger.split('_')[1], messenger.split('_')[0]);
 });

 // list view buttons
 $('.listView-x').on('click', function () {
     $('.messenger-listView').hide();
 });
 $('.show-listView').on('click', function () {
     $('.messenger-listView').show();
 });

 // click action for [add to favorite] button.
 $('.add-to-favorite').on('click', function () {
     star(messenger.split('_')[1]);
 });

 // calling Css Media Queries
 cssMediaQueries();

 // message form on submit.
 $('#message-form').on('submit', (e) => {
     e.preventDefault();
     sendMessage();
 });

 // message input on keyup [Enter to send, Enter+Shift for new line]
 $('#message-form .m-send').on('keyup', (e) => {
     // if enter key pressed.
     if (e.which == 13 || e.keyCode == 13) {
         // if shift + enter key pressed, do nothing (new line).
         // if only enter key pressed, send message.
         if (!e.shiftKey) {
             triggered = isTyping(false);
             sendMessage();
         }
     }
 });

 // On [upload attachment] input change, show a preview of the image/file.
 $('body').on('change', ".upload-attachment", (e) => {
     let file = e.target.files[0];
     let reader = new FileReader();
     let sendCard = $('.messenger-sendCard');
     reader.readAsDataURL(file);
     reader.addEventListener('loadstart', (e) => {
         $('#message-form').before(loadingSVG());
     });
     reader.addEventListener('load', (e) => {
         $('.messenger-sendCard').find('.loadingSVG').remove();
         if (!file.type.match("image.*")) {
             // if the file not image
             sendCard.find('.attachment-preview').remove(); // older one
             sendCard.prepend(attachmentTemplate('file', file.name));
         } else {
             // if the file is an image
             sendCard.find('.attachment-preview').remove(); // older one
             sendCard.prepend(attachmentTemplate('image', file.name, e.target.result));
         }
     });
 });

 // Attachment preview cancel button.
 $('body').on('click', ".attachment-preview .cancel", (e) => {
     cancelAttachment();
 });

 // typing indicator on [input] keyDown
 $('#message-form .m-send').on('keydown', () => {
     if (typingNow < 1) {
         // Trigger typing
         let triggered = isTyping(true);
         /*triggered ? console.info('[+] Triggered')
             : console.error('[+] Not triggered');*/
         // Typing now
         typingNow = 1;
     }
     // Clear typing timeout
     clearTimeout(typingTimeout);
     // Typing timeout
     typingTimeout = setTimeout(function () {
         triggered = isTyping(false);
         /*triggered ? console.info('[-] Triggered')
             : console.error('[-] Not triggered');*/
         // Clear typing now
         typingNow = 0;
     }, 1000);
 });

 // Image modal
 $('body').on('click', ".chat-image", function () {
     let src = $(this).css("background-image").split(/"/)[1];
     $("#imageModalBox").show();
     $("#imageModalBoxSrc").attr('src', src);
 });
 $('.imageModal-close').on('click', function () {
     $("#imageModalBox").hide();
 });

 // Search input on focus
 $('.messenger-search').on('focus', function () {
     $('.messenger-tab').hide();
     $('.messenger-tab[data-view="search"]').show();
 });
 // Search action on keyup
 $('.messenger-search').on('keyup', function (e) {
     $.trim($(this).val()).length > 0
         ? $('.messenger-search').trigger('focus') + messengerSearch($(this).val())
         : $('.messenger-tab').hide() +
         $('.messenger-listView-tabs a[data-view="users"]').trigger('click');
 });

 // Delete Conversation button
 $('.messenger-infoView-btns .delete-conversation').on('click', function () {
     app_modal({
         name: 'delete',
     });
 });
 // delete modal [delete button]
 $('.app-modal[data-name=delete]').find('.app-modal-footer .delete').on('click', function () {
     deleteConversation(messenger.split('_')[1]);
     app_modal({
         show: false,
         name: 'delete',
     });
 });
 // delete modal [cancel button]
 $('.app-modal[data-name=delete]').find('.app-modal-footer .cancel').on('click', function () {
     app_modal({
         show: false,
         name: 'delete',
     });
 });

 // Settings button action to show settings modal
 $('.settings-btn').on('click', function () {
     app_modal({
         name: 'settings',
     });
 });

 // on submit settings' form
 $('#updateAvatar').on('submit', (e) => {
     e.preventDefault();
     updateSettings();
 });
 // Settings modal [cancel button]
 $('.app-modal[data-name=settings]').find('.app-modal-footer .cancel').on('click', function () {
     app_modal({
         show: false,
         name: 'settings',
     });
     cancelUpdatingAvatar();
 });
 // upload avatar on change
 $('body').on('change', ".upload-avatar", (e) => {
     // store the original avatar
     if (defaultAvatarInSettings == null) {
         defaultAvatarInSettings = $('.upload-avatar-preview').css('background-image');
     }
     let file = e.target.files[0];
     let reader = new FileReader();
     reader.readAsDataURL(file);
     reader.addEventListener('loadstart', (e) => {
         $('.upload-avatar-preview').append(loadingSVG('42px', 'upload-avatar-loading'));
     });
     reader.addEventListener('load', (e) => {
         $('.upload-avatar-preview').find('.loadingSVG').remove();
         if (!file.type.match("image.*")) {
             // if the file is not an image
             // console.error('File you selected is not an image!');
         } else {
             // if the file is an image
             $('.upload-avatar-preview').css('background-image', 'url("' + e.target.result + '")');
         }
     });
 });
 // change messenger color button
 $('body').on('click', '.update-messengerColor a', function () {
     messengerColor = $(this).attr('class').split(' ')[0];
     $('.update-messengerColor a').removeClass('m-color-active');
     $(this).addClass('m-color-active');
 });
 // Switch to Dark/Light mode
 $('body').on('click', '.dark-mode-switch', function () {
     if ($(this).attr('data-mode') == '0') {
         $(this).attr('data-mode', '1');
         $(this).removeClass('far');
         $(this).addClass('fas');
         dark_mode = 'dark';
     } else {
         $(this).attr('data-mode', '0');
         $(this).removeClass('fas');
         $(this).addClass('far');
         dark_mode = 'light';
     }
 });
});

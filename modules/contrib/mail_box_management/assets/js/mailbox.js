let MAILBOX_CONTENT_LIST = [];
let BACKGROUND_LOCK = 0;
let CONTENT_REFRESHING_LOCK = 0;
let UI_REFRESHING_LOCK = 0;
let COMPOSER_ATTACHMENTS_FILES = [];

window.addEventListener('DOMContentLoaded', function() {
  mail_box_panel();
  setTimeout(() => {
    startBackgroundWorkers();
  }, 5000);
});

function mail_box_panel(){

  const mailboxes_tags = document.getElementsByClassName('mailboxes');
  const nextNav = document.getElementsByClassName('next');
  const prevNav = document.getElementsByClassName('prev');
  const backgroundTriggers = document.getElementById('background-tasks-triggers');
  const create_mailbox = document.getElementsByClassName('create-mailbox');
  const search_box = document.querySelector('#search-box');

  if(mailboxes_tags.length > 0){
    Array.from(mailboxes_tags).forEach(tag=>{
      tag.addEventListener('click', function(e){
        handling_mailbox_tag(e, tag);
      })
    })
  }

  if(prevNav.length > 0){
    Array.from(prevNav).forEach(tag=>{
      tag.addEventListener('click', function(e){
        navigatePrev(e, tag);
      })
    })
  }
  if(nextNav.length > 0){
    Array.from(nextNav).forEach(tag=>{
      tag.addEventListener('click', function(e){
        navigateNext(e, tag);
      })
    })
  }

  setTimeout(()=>{
    if(backgroundTriggers){
      backgroundTriggersHandler(backgroundTriggers);
    }
  }, 2000);

  if(create_mailbox.length > 0){
    Array.from(create_mailbox).forEach(mailbox=>{
      mailbox.addEventListener('click', function(e){
        handleMailBoxCreation(mailbox);
      })
    })
  }

  if(search_box !== null) {
    handling_search_box(search_box)
  }

  composer();
}

function handling_mailbox_tag(e, tag){
  e.preventDefault()
   const box_name = tag.getAttribute('data-name');
   const correspond_header = document.querySelector('ul[data-name="'+box_name+'"]');
   const correspond_pager = document.querySelector('div[data-pager-name="'+box_name+'"]');
   const mailboxes_headers = document.getElementsByClassName('mailboxes-headers');
   const pagers = document.getElementsByClassName('navigation-mailbox');
   if(mailboxes_headers.length > 0){
     Array.from(mailboxes_headers).forEach(ele=>{
       ele.classList.remove('ps');
       ele.classList.remove('ps--active-y');
       ele.classList.add('d-none');
     })
   }
   if(pagers.length > 0){
     Array.from(pagers).forEach(ele=>{
       ele.classList.add('d-none');
     })
   }
   correspond_pager.classList.remove('d-none');
   correspond_header.classList.remove('d-none');
   correspond_header.classList.add('ps');
   correspond_header.classList.add('ps--active-y');
}

function navigatePrev(e, element) {
  e.preventDefault();
}

function navigateNext(e, element) {
  e.preventDefault();
}

function callMailContentLoader(e, emitter, callback) {
  const tag_name = e.tagName.toLowerCase();
  const trgs = ['li', 'h4', 'span', 'div', 'p', 'h6'];
  let msgno = null;
  let box_name = null;

  if (trgs.includes(tag_name)) {
    const li = tag_name !== 'li' ? e.closest('li') || e.closest('div').closest('li') : e;
    if (li) {
      msgno = li.getAttribute('data-message')?.trim();
      box_name = li.parentElement.getAttribute('data-name');
    }
  }

  if (msgno && box_name) {
    const correspond_header = document.querySelector(`a[data-name="${box_name}"]`);
    box_name = correspond_header ? correspond_header.getAttribute('box-path') : null;

    const mailContent = document.getElementById('mailContent');
    if (mailContent) {
      const loadingDiv = document.querySelector('div[data-loading="loading"]');
      if (loadingDiv) {
        mailContent.innerHTML = loadingDiv.innerHTML;
        if(mailContent.querySelector('.mailcontent-placeholder')) {
          mailContent.querySelector('.mailcontent-placeholder').classList.remove('d-none');
        }
        const statusText = mailContent.querySelector('h5');
        if (statusText) {
          statusText.textContent = 'Fetching...';
        }
      }

      if(MAILBOX_CONTENT_LIST.length > 0) {
        let found_mails = null;
        for (let i = 0; i < MAILBOX_CONTENT_LIST.length; i++) {
          if(MAILBOX_CONTENT_LIST[i][box_name]) {
            found_mails = MAILBOX_CONTENT_LIST[i][box_name];
            break;
          }
        }
        if(found_mails) {
          let this_email_clicked = null;
          for (let i = 0; i < found_mails.length; i++) {
            if(found_mails[i][msgno.trim()]) {
              this_email_clicked = found_mails[i][msgno.trim()];
              break;
            }
          }
          if(this_email_clicked && this_email_clicked.status === true) {
            mailContent.innerHTML = this_email_clicked.content;
            callback(emitter);
            return;
          }

        }
      }

      fetch('/mailbox-management/content/loader', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ box_name, msgno }),
      })
        .then(response => {
          if (!response.ok) {
            throw new Error('Network response was not ok');
          }
          return response.json();
        })
        .then(data => {
          if (data.status === true) {
            mailContent.innerHTML = data.content;
            callback(emitter);
          } else {
            throw new Error('Failed to load content from server');
          }
        })
        .catch(() => {
          const errorDiv = document.querySelector('div[data-error="error"]');
          mailContent.innerHTML = errorDiv ? errorDiv.innerHTML : '<div class="error">An error occurred while loading</div>';
        });
    }
  }
}

function updateFlag(e) {
  const span = e.tagName.toLowerCase() !== 'span' ? e.closest('span') || e.closest('i').closest('span') : e;
  const msgno = span.closest('div').closest('div').closest('li').getAttribute('data-message');
  let box_name = span.closest('div').closest('div').closest('li').closest('ul').getAttribute('data-name');
  const correspond_header = document.querySelector(`a[data-name="${box_name}"]`);
  const flag = span.getAttribute('data-flag');
  box_name = correspond_header ? correspond_header.getAttribute('box-path') : null;

  if(flag && box_name && msgno) {
    const xhr = new XMLHttpRequest();
    showToast('Wait we are sending '+flag+' action')
    xhr.open('POST', '/admin/mailbox-management/content/flag/action', true);
    xhr.onload = function (){
      if(this.status === 200) {
        showToast('Successfully updated flag!');
      }
      else {
        showToast('Failed to update flag');
      }
    }
    xhr.send(JSON.stringify({box_name, msgno, flag}));
  }
}

function showToast(message, time = 3000) {
  const toast = document.getElementById("toast");
  toast.textContent = message;

  // Show the toast
  toast.classList.add("show");

  // Hide the toast after 3 seconds
  setTimeout(() => {
    toast.classList.remove("show");
  }, time);
}

function singleAddListener(e, obj = null) {
 const a_tag = e.closest('a');
 const msgno = a_tag.getAttribute('data-msgno');
 const box_name = a_tag.closest('nav').getAttribute('data-boxname');
 const action = a_tag.getAttribute('data-action');
 console.log(msgno,box_name,action);
 if(action === 'FLAGGED' || action === 'UNFLAGGED') {
   if(action && box_name && msgno) {
     const xhr = new XMLHttpRequest();
     showToast('Wait we are sending '+action+' action')
     xhr.open('POST', '/mailbox-management/content/flag/action', true);
     xhr.onload = function (){
       if(this.status === 200) {
         showToast('Successfully updated flag! loading in 5 seconds');
         setTimeout(()=>{
           window.location.reload();
         },5000);
       }
       else {
         showToast('Failed to update flag');
       }
     }
     xhr.send(JSON.stringify({box_name, msgno, flag: action}));
   }
 }

 else if (action === 'delete') {
   if( box_name && msgno) {
     const xhr = new XMLHttpRequest();
     showToast('Wait we are sending '+action+' action')
     xhr.open('POST', '/mailbox-management/content/mail/action', true);
     xhr.onload = function (){
       if(this.status === 200) {
         showToast('Successfully delete email reloading in 5 seconds');
         setTimeout(()=>{
           window.location.reload();
         },5000);
       }
       else {
         showToast('Failed to delete email');
       }
     }
     xhr.send(JSON.stringify({box_name, msgno}));
   }
 }

 else if(action === 'reply') {
   OPEN_REPLY(a_tag,obj);
 }
}

function startBackgroundWorkers() {
  MAILBOX_CONTENT_LIST = [];
  const mailboxes = document.getElementsByClassName('mailboxes');
  if (mailboxes.length > 0) {
    showToast("Loading mails contents");
    const spinner = backgroundSpinner('Content refreshing..');
    let i = 0;
    const time = setInterval(()=> {
     if(BACKGROUND_LOCK === 0 && mailboxes.length > i) {
       const mailboxName = mailboxes[i].getAttribute('data-name');
       const boxUrl = mailboxes[i].getAttribute('box-path');
       if (mailboxName && boxUrl) {
         BACKGROUND_LOCK = 1;
         CONTENT_REFRESHING_LOCK = 1;
         const worker = new Worker('/modules/contrib/mail_box_management/assets/js/worker.js');
         // Handle messages from the worker
         worker.onmessage = (event) => {
           const { status, message, data } = event.data;

           if (status === 'success') {
             MAILBOX_CONTENT_LIST.push(data.list);
           }else {
             console.log(message);
           }

           // Terminate the worker after it finishes its job
           if (status === 'success' || status === 'error') {
             worker.terminate();
             i = i + 1;
             BACKGROUND_LOCK = 0;
           }
         };

         // PostService data to the worker
         worker.postMessage(
           JSON.stringify({
             MailboxName: mailboxName,
             MailboxUrl: boxUrl,
           })
         );
       } else {
         console.error(`Invalid mailbox configuration for element at index ${i}`);
         i = i + 1;
       }
     }else {
       if(mailboxes.length < i) {
         clearInterval(time);
       }
     }
    }, 2000);

    const verifyDone = setInterval(()=>{
      if(MAILBOX_CONTENT_LIST.length > 0){
        if(MAILBOX_CONTENT_LIST.length !== mailboxes.length){
          showToast("Loading mailboxes contents is in progress")
        }else {
          clearInterval(verifyDone);
         setTimeout(()=>{
           CONTENT_REFRESHING_LOCK = 0;
           spinner.remove();
           showToast("Done loading mailboxes contents");
         },5000);
        }
      }
    },3000);
  } else {
    console.log('No mailboxes found.');
  }
}

function backgroundTriggersHandler(element) {
  const a_tags = element.querySelectorAll('a');
  a_tags.forEach(tag => {
    const i_tag = tag.querySelector('i');
    const span = tag.querySelector('span');
    if(i_tag) {
      i_tag.addEventListener('click', (e) => {
       startTask(tag.getAttribute('data-action'))
      })
    }
    if(span) {
      span.addEventListener('click', (e) => {
        startTask(tag.getAttribute('data-action'))
      })
    }
  })
}

function refreshMailBoxes() {
  const compose_form = document.getElementById('compose-mail-form');
  if(compose_form && !compose_form.classList.contains('d-none')) {
    compose_form.classList.add('d-none');
    document.querySelector('.menu-compose').classList.remove('d-none');
    showToast('Compose form is active you need to copy your composed mail first', 5000);
    return;
  }
  if(UI_REFRESHING_LOCK !== 0) {
    showToast('This task is already in progress');
    return;
  }
  UI_REFRESHING_LOCK = 1;
  const spinner = backgroundSpinner('UI refreshing..');
  showToast('Ui refreshing has started you are advise not use the panel',5000);
  const xhr = new XMLHttpRequest();
  xhr.open('GET', '/mailbox_management/ui/refresh', true);
  xhr.timeout = 0;
  xhr.onload = function (){
    if(this.status === 200) {
      const full = document.querySelector('#mail-box-container-full');
      full.innerHTML =  JSON.parse(this.responseText).content;
      UI_REFRESHING_LOCK = 0;
      REBOOT_FUNCTION();
      mail_box_panel();
      startBackgroundWorkers();
    }else {
      UI_REFRESHING_LOCK = 0;
      spinner.remove();
      showToast('Failed to refresh ui');
    }
  }
  xhr.send();
}

function backgroundSpinner(name) {
  const a = document.createElement('a');
  a.classList.add('nav-link');
  const i_tag = document.createElement('i');
  i_tag.classList.add('ri-refresh-line');
  const span = document.createElement('span');
  span.textContent = name;
  a.appendChild(i_tag);
  a.appendChild(span);
  document.querySelector('#ongoing-background-tasks').appendChild(a);
  return a;
}
function startTask(task_name) {
  if(task_name === 'refresh-content') {
    if(CONTENT_REFRESHING_LOCK !== 0) {
      showToast('This task is already in progress');
    }else {
      startBackgroundWorkers();
    }
  }
  else if (task_name === 'refresh-mails') {
    refreshMailBoxes();
  }
}

function composer() {
  const compose_form = document.getElementById('composer-form');
  if(compose_form) {
    const submit = document.getElementById('send-email');
    if(submit) {
      submit.addEventListener('click', (e) => {
        e.preventDefault();
        document.querySelector("#mail-form").requestSubmit();
      });

      let try_again = false;
      document.querySelector("#mail-form").addEventListener('submit', (e) => {
        e.preventDefault();
        const recipient = compose_form.querySelector('.form-group > input[name="to_email"]');
        const subject = compose_form.querySelector('.form-group > input[name="subject"]');
        const content = compose_form.querySelector('.form-group > textarea[name="content"]');
        const xhr = new XMLHttpRequest();
        xhr.open('POST', '/mailbox_management/compose/mail', true);
        xhr.timeout = 0;
        xhr.onload = function (){
          if(this.status === 200) {
            try{
              const data = JSON.parse(this.responseText);
              showToast(data.msg);
            }catch (e) {
              showToast('Something went wrong!');
            }
          }else {
            try{
              const data = JSON.parse(this.responseText);
              if(try_again === false) {
                document.querySelector("#mail-form").requestSubmit();
                try_again = true;
                return;
              }
              showToast(data.msg);
            }catch (e) {
              showToast('Something went wrong!');
            }
          }
        }
        showToast("Sending email please wait.");
        xhr.send(JSON.stringify({
          recipient: recipient.value,
          subject: subject.value,
          content: content.value,
          attachments: COMPOSER_ATTACHMENTS_FILES
        }));
        if(document.querySelector('.nav-link-close')) {
          document.querySelector('.nav-link-close').click();
        }
      })

      document.getElementById('add-attachment').addEventListener('click', function (e) {
        e.preventDefault();
        const fileInput = document.getElementById('file-input');
        fileInput.click();
      });
      document.getElementById('file-input').addEventListener('change', function () {
        const link_tag = document.getElementById('add-attachment-loading');
        link_tag.classList.remove('d-none');
        const maxFiles = 10;
        const maxFileSize = 10 * 1024 * 1024;
        const files = Array.from(this.files);

        if (files.length > maxFiles) {
          link_tag.classList.add('d-none');
          showToast(`You can select a maximum of ${maxFiles} files.`,5000);
          return;
        }

        files.forEach((file, index) => {
          if (file.size > maxFileSize) {
            showToast(`File ${file.name} exceeds the size limit of 10MB.`);
            return;
          }
          const reader = new FileReader();

          reader.onload = function (event) {
            const base64 = event.target.result.split(',')[1];
            const file_object = { name: file.name, size: file.size, type: file.type, base64: base64, file: `${index + 1}`};
            COMPOSER_ATTACHMENTS_FILES.push(file_object);
            listFileUploaded(file_object);
          };

          reader.onerror = function (error) {
            console.error('Error reading file:', file.name, error);
          };

          // Start reading the file as a data URL
          reader.readAsDataURL(file);
        });
        link_tag.classList.add('d-none');
      });
    }
  }
}

function reply_composer(message_id, msgno,subject_title,to_mail,box_name) {
  const compose_form = document.querySelector('#reply-composer-form');
  compose_form.querySelector('.form-group > input[name="message_id"]').value = message_id;
  compose_form.querySelector('.form-group > input[name="subject"]').value = "Re: "+ subject_title;
  compose_form.querySelector('.form-group > input[name="msgno"]').value = msgno;
  compose_form.querySelector('.form-group > input[name="to_mail"]').value = to_mail;
  compose_form.querySelector('.form-group > input[name="box_name"]').value = box_name;
  if(compose_form) {
    const submit = document.getElementById('send-email-reply');
    if(submit) {
      submit.addEventListener('click', (e) => {
        e.preventDefault();
        document.querySelector("#reply-form").requestSubmit();
      });

      let try_again = false;
      document.querySelector("#reply-form").addEventListener('submit', (e) => {
        e.preventDefault();
        const recipient = compose_form.querySelector('.form-group > input[name="message_id"]');
        const subject = compose_form.querySelector('.form-group > input[name="subject"]');
        const content = compose_form.querySelector('.form-group > textarea[name="content"]');
        const msgno_v =  compose_form.querySelector('.form-group > input[name="msgno"]');
        const to_mail_v =  compose_form.querySelector('.form-group > input[name="to_mail"]');
        const box =  compose_form.querySelector('.form-group > input[name="box_name"]');
        console.log(content.textContent);
        const xhr = new XMLHttpRequest();
        xhr.open('POST', '/mailbox_management/compose/mail/reply', true);
        xhr.timeout = 0;
        xhr.onload = function (){
          if(this.status === 200) {
            try{
              const data = JSON.parse(this.responseText);
              showToast(data.msg);
              window.location.reload();
            }catch (e) {
              showToast('Something went wrong!');
            }
          }else {
            try{
              const data = JSON.parse(this.responseText);
              if(try_again === false) {
                document.querySelector("#reply-form").requestSubmit();
                try_again = true;
                return;
              }
              showToast(data.msg);
            }catch (e) {
              showToast('Something went wrong!');
            }
          }
        }
        showToast("Sending reply email")
        xhr.send(JSON.stringify({
          message_id: recipient.value,
          subject: subject.value,
          content: content.value,
          msgno: msgno_v.value,
          recipient: to_mail_v.value,
          attachments: COMPOSER_ATTACHMENTS_FILES,
          box_name: box.value,
        }));
        if(document.querySelector('.nav-link-close')) {
          Array.from(document.querySelectorAll('.nav-link-close')).forEach((cl)=>cl.click());
        }
      })

      document.getElementById('add-attachment-reply').addEventListener('click', function (e) {
        e.preventDefault();
        const fileInput = document.getElementById('file-input');
        fileInput.click();
      });
      document.getElementById('file-input').addEventListener('change', function () {
        const link_tag = document.getElementById('add-attachment-loading-reply');
        link_tag.classList.remove('d-none');
        const maxFiles = 10;
        const maxFileSize = 10 * 1024 * 1024;
        const files = Array.from(this.files);

        if (files.length > maxFiles) {
          link_tag.classList.add('d-none');
          showToast(`You can select a maximum of ${maxFiles} files.`,5000);
          return;
        }

        files.forEach((file, index) => {
          if (file.size > maxFileSize) {
            showToast(`File ${file.name} exceeds the size limit of 10MB.`);
            return;
          }
          const reader = new FileReader();

          reader.onload = function (event) {
            const base64 = event.target.result.split(',')[1];
            const file_object = { name: file.name, size: file.size, type: file.type, base64: base64, file: `${index + 1}`};
            COMPOSER_ATTACHMENTS_FILES.push(file_object);
            listFileUploaded(file_object, 'attachments-files-replay');
          };

          reader.onerror = function (error) {
            console.error('Error reading file:', file.name, error);
          };

          // Start reading the file as a data URL
          reader.readAsDataURL(file);
        });
        link_tag.classList.add('d-none');
      });
    }
  }
}

function listFileUploaded(file, element_id='attachments-files') {
  const attachmentsContainer = document.getElementById(element_id);
  const fileLink = document.createElement('a');
  fileLink.className = 'd-block ri-close-fill';
  fileLink.href = 'javascript:void(0);';
  fileLink.innerHTML = `<span> ${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)</span>`;
  fileLink.addEventListener('click', function () {
    for (let i = 0; i < COMPOSER_ATTACHMENTS_FILES.length; i++) {
      if(file.name === COMPOSER_ATTACHMENTS_FILES[i].name) {
        COMPOSER_ATTACHMENTS_FILES.splice(i, 1);
        break;
      }
    }
    fileLink.remove();
  });
  attachmentsContainer.appendChild(fileLink);
}

function handleMailBoxCreation(element) {
  const boxType = element.getAttribute('data-boxtype');
  console.log(boxType);
  if(boxType === 'main' || boxType === 'label') {
    const dialog = document.querySelector('#create-box');
    if (dialog) {
      const form = dialog.querySelector('form');
      const hidden = document.createElement('input');
      dialog.querySelector('button').addEventListener('click', function () {
        dialog.close();
      })
      if(form.querySelector("#hidden-type")) {
        form.querySelector("#hidden-type").remove();
      }
      hidden.type = 'hidden';
      hidden.value = boxType;
      hidden.id = "hidden-type"
      hidden.name = 'type';
      form.appendChild(hidden);
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        const form_data = new FormData(form);
        createBox({
          type: form_data.get('type'),
          parent:form_data.get('parent'),
          title: form_data.get('title'),
        });
      })
      dialog.showModal();
    }
  }
}

function createBox(data) {
  const xhr = new XMLHttpRequest();
  xhr.open('POST', '/mailbox_management/mailboxes/create/new', true);
  xhr.onload = function () {
    if (xhr.status === 200) {
      console.log(xhr.responseText);
    }
  }
  xhr.send(JSON.stringify(data));
}

function sendTopAction(data) {
  const xhr = new XMLHttpRequest();
  xhr.open('POST', '/mailbox_management/mailboxes/mail/top/action', true);
  xhr.onload = function () {
    if (xhr.status === 200) {
      console.log(xhr.responseText);
    }
  }
  xhr.send(JSON.stringify(data));
}

function handleTopAction() {
  const top_actions = document.getElementsByClassName('top-actions');
  if (top_actions){
    console.log(top_actions);
    Array.from(top_actions).forEach(action=>{
      action.addEventListener('click', function(e){
        e.preventDefault();
        const title = action.getAttribute('title');
        const dialog = document.querySelector('#top-actions');
        const msgno = action.getAttribute('data-msgno');
        const source = action.getAttribute('data-source');
        if(title && dialog && msgno && source){
          if (dialog) {
            const form = dialog.querySelector('form');
            const hidden = document.createElement('input');
            const hidden2 = document.createElement('input');
            const hidden3 = document.createElement('input');
            dialog.querySelector('button').addEventListener('click', function () {
              dialog.close();
            })
            if (form.querySelector("#hidden-top-action")) {
              form.querySelector("#hidden-top-action").remove();
            }
            if (form.querySelector("#msgno")) {
              form.querySelector("#msgno").remove();
            }
            if (form.querySelector("#hidden-top-source")) {
              form.querySelector("#hidden-top-source").remove();
            }
            hidden.type = 'hidden';
            hidden.value = title;
            hidden.id = "hidden-top-action"
            hidden.name = 'type';
            form.appendChild(hidden);
            hidden2.type = 'hidden';
            hidden2.value = msgno;
            hidden2.id = "msgno"
            hidden2.name = 'msgno';
            form.appendChild(hidden2);
            hidden3.type = 'hidden';
            hidden3.value = source;
            hidden3.id = "hidden-top-source"
            hidden3.name = 'source';
            form.appendChild(hidden3);
            form.addEventListener('submit', function (e) {
              console.log(form)
              e.preventDefault();
              const form_data = new FormData(form);
              sendTopAction({
                type: form_data.get('type'),
                destination: form_data.get('destination').trim(),
                msgno: form_data.get('msgno').trim(),
                source: form_data.get('source').trim(),
              });
            })
            dialog.showModal();
          }
        }
      })

      })
  }
}

function handling_search_box(input_element) {
  input_element.addEventListener('blur',(e)=>{
    const link = document.querySelector('a.active.mailboxes');
    if(input_element.value.length > 0 && link !== null) {
      const mailbox = link.getAttribute('box-path');
      const params = new URLSearchParams({i: input_element.value, mailbox: mailbox});
      const xhr = new XMLHttpRequest();
      xhr.open('GET', '/mailbox_management/mail/search?'+params.toString(),true);
      xhr.onload = function (){
        try{
          const search = JSON.parse(xhr.responseText);
          if(search.hasOwnProperty('status') && search.status === true) {
            const ul_container = document.querySelectorAll(".mailboxes-headers");
            let with_name = null;
            for (let i = 0; i < ul_container.length; i++) {
              const name = ul_container[i].getAttribute('data-name').trim();
              const name_2 = link.getAttribute('data-name');
              if(name === name_2) {
                with_name = ul_container[i];
              }
            }

            if(with_name !== null && search.hasOwnProperty('content') && search.content.length > 10) {
              with_name.innerHTML =search.content;
              showToast("Content found ("+search.total+")");
              REBOOT_FUNCTION();
              mail_box_panel();
            }else {
             showToast("No Content found ("+input_element.value+")")
            }
          }
        }catch (e) {

        }
      }
      xhr.send();
    }
  })
}


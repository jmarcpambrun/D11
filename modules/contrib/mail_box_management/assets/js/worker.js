// Function to fetch mailbox content using XMLHttpRequest
function fetchMailboxContent(mailboxName, mailboxUrl) {
  return new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest();
    xhr.open('POST', '/mailbox-management/worker/background', true);
    xhr.setRequestHeader('Content-Type', 'application/json');
    xhr.timeout = 0;
    xhr.onload = function () {
      if (xhr.status === 200) {
        try {
          const data = JSON.parse(xhr.responseText);
          resolve(data);
        } catch (error) {
          reject(new Error(`Failed to parse response for mailbox: ${mailboxName}`));
        }
      } else {
        reject(new Error(`${mailboxName} content not found (HTTP ${xhr.status})`));
      }
    };
    xhr.onerror = function () {
      reject(new Error(`Network error occurred while fetching content for ${mailboxName}`));
    };
    xhr.ontimeout = function () {
      reject(new Error(`Connection timed out after ${timeout / 1000} seconds for mailbox: ${mailboxName}`));
    };
    xhr.send(JSON.stringify({ mailbox_name: mailboxUrl }));
  });
}

// Handle messages received by the worker
onmessage = async (event) => {
  try {
    const { MailboxName: mailboxName, MailboxUrl: mailboxUrl } = JSON.parse(event.data);
    if (!mailboxName || !mailboxUrl) {
      throw new Error('Invalid input data: MailboxName and MailboxUrl are required.');
    }
    const data = await fetchMailboxContent(mailboxName, mailboxUrl);
    postMessage({ status: 'success', data });
  } catch (error) {
    postMessage({ status: 'error', message: error.message });
  }
};

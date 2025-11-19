async function loadBursar() {
  const data = await fetchData('fetch_billing.php');
  const balance = parseFloat(data.balance || 0).toLocaleString('en-US', {
    style: 'currency',
    currency: 'USD'
  });
  const nextDue = data.next_due || 'N/A';
  const updated = new Date().toLocaleString();

  pageContent.innerHTML = `
    <h2>💳 Bursar / Billing</h2>
    <p>Here you can review your financial information and make payments.</p>
    <ul>
      <li>Current Balance: <strong>${balance}</strong></li>
      <li>Next Payment Due: ${nextDue}</li>
    </ul>
    <button class="btn">Pay Now</button>
    <button class="btn outline">View Statement</button>
    <p class="small text-muted">Last updated: ${updated}</p>
  `;
}

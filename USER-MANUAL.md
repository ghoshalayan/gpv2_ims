# GP ENTERPRISE IMS — User Manual for the New Features

For everyone using the system day to day. It covers only what is **new or changed** in this
update; everything else works exactly as before.

Wherever you see **Sidebar → Group → Item**, that is the menu path on the left.

---

## What changed, in one line each

| New / changed | Where to find it |
|---|---|
| **Due List** — see who owes you, split by Short-term / Long-term | Reports → Ledgers → **Due List** |
| **Returned Salesman A/c** — goods returned per salesman | Reports → Ledgers → **Returned Salesman A/c** |
| **Rate Management** — change many selling prices at once | Menus → Product Management → **Rate Management** |
| **Brand rate slabs** — auto-suggest a purchase rate from the quantity | Menus → Product Management → **Brands** → *Slabs* |
| **Passbook Print & WhatsApp** — send a statement to a customer | Any customer/supplier/salesman → **Passbook** |
| **Better product search** — filter by category, stock and price | Menus → Product Management → **Products** |
| **Commission per sale** — set a different rate for one sale | While building a Sales Invoice |
| **Phone is now optional** — on customers and suppliers | Customer / Supplier form |

> **A note on "Print / PDF" throughout this manual.** Buttons labelled **Print / PDF** open a
> clean printable page. To get a PDF file, press **Ctrl+P** (or ⌘+P on Mac) and choose
> **"Save as PDF"** as the printer instead of a physical printer. That file can then be
> emailed or attached in WhatsApp.

---

## 1. Due List — who owes you money

**Sidebar → Reports → Ledgers → Due List**

This screen did not exist before. It has two tabs.

### 1.1 The Customers tab

Shows every customer who currently owes money, highest first.

| Column | Meaning |
|---|---|
| **Bills** | How many unpaid bills make up the amount |
| **Short-term** / **Long-term** / **Untagged** | The split you control — see 1.2 |
| **Total Due** | What they owe in total |
| **Oldest** | Date of their oldest unpaid bill — the ageing warning sign |
| **Default term** | Sets the customer's usual term — see 1.2 |
| **Passbook** | Opens their full statement |

**To use it**
1. Type a name or phone in **Search**, or leave blank to see everyone.
2. Use **Due term** to show only *Short-term*, *Long-term* or *Untagged* dues.
3. Press **Apply**.

> Customers holding an unused advance are shown with a green note under their name. That
> advance is **not** subtracted from the Total Due — it is kept separate so the figure
> continues to match the Balance Sheet.

### 1.2 Marking dues Short-term or Long-term

The system does not guess this. **You decide**, in either of two ways.

**Option A — set it once for a customer (fastest)**
In the **Default term** column, pick *Short-term* or *Long-term*. It saves immediately and
applies to all of that customer's bills that you have not marked individually.

**Option B — mark one particular bill**
Use this when a single bill is different from the customer's normal terms. A bill's own
setting always beats the customer default.

**"Untagged"** simply means nobody has decided yet. Untagged money is still counted in
**Total Due** — it just is not attributed to either bucket. Working the Untagged column down
to zero is a good habit.

### 1.3 The Salesmen tab

Salesmen can owe money *and* be owed money, so this tab shows both.

| Column | Meaning |
|---|---|
| **Commission** | What **you owe the salesman** — earned, minus returns, minus what you already paid out |
| **Cash** | What **the salesman owes you** on their own customer account |
| **Total** | Commission minus Cash |
| **Direction** | Plain-language answer: *we owe* / *owes us* / *settled* |

**Read the Total like this:** a positive figure means you owe them; a negative figure (shown
in red) means they owe you. The **Direction** column says which, so you never have to work
it out from the sign.

### 1.4 Printing or exporting

- **Print / PDF** — a clean statement with your business header. Ctrl+P → Save as PDF.
- **Export CSV** — opens in Excel for further working.

Both respect whatever tab, search and filter you currently have on screen — so filter first,
then print.

---

## 2. Returned Salesman Account

**Sidebar → Reports → Ledgers → Returned Salesman A/c**

Shows goods that came back against sales a salesman was credited for, and the commission
reversed with them. Useful for spotting a salesman with an unusual level of returns.

1. Set **From** and **To** dates (defaults to the current financial year), then **Apply**.
2. The table lists each salesman with **Returns**, **Qty**, **Value**, **Cost** and
   **Commission reversed**.
3. Click the list icon in the **Detail** column to see the individual returned items —
   return number, original invoice, customer, quantity and the commission reversed on each.

When you open one salesman's detail, the header also tells you how much commission they
*earned* in the same period, so a reversal can be judged in proportion rather than in
isolation.

> These reversals are already reflected in the salesman's Passbook. This screen breaks them
> out for review; it does not add a second charge.

---

## 3. Rate Management — changing many selling prices at once

**Sidebar → Menus → Product Management → Rate Management**

Instead of editing products one by one, change a whole category, sub-category or brand
together. **Nothing is saved until you review a preview and confirm.**

### 3.1 Making a change

1. Narrow down what you want to change using **Category**, **Sub-category** and **Brand**.
   Leave a box on "All" to not narrow by it.
2. Choose a **Change type**:
   - **By percentage (%)** — e.g. `5` raises by 5%, `-5` reduces by 5%
   - **By flat amount (₹)** — e.g. `20` adds ₹20, `-20` takes ₹20 off
   - **Set to fixed price (₹)** — sets every matching product to the same price
3. Enter the **Value**. *Negative reduces the price.*
4. Tick **Round to nearest rupee** if you want tidy prices.
5. Press **Preview changes**.

### 3.2 Reading the preview

You get the exact list of products that would change, with **Current**, **New** and the
**Change** for each.

> **Watch the Margin column.** If a new price falls below what the stock cost you, it is
> shown in red as *(below cost)*. Check those before confirming.

Some products may be listed as **skipped** — click that line to see why. The two reasons are
*"no change"* (the new price equals the old one) and *"would go below zero"*.

### 3.3 Applying and undoing

1. Add a **Remarks** note — e.g. *"April price revision from Berger"*. Worth doing; it is
   what you will read months later.
2. Press **Apply to N product(s)** and confirm.

Every change is recorded under **Recent rate changes** at the bottom. To undo one, press
**Revert** on its row and confirm — every product in that batch goes back to its previous
price.

> **If Revert refuses:** it means one or more of those products has been re-priced since the
> batch ran. Rather than silently wiping out that newer, deliberate price, the system stops
> and names the products. Fix those individually instead.

Only the **selling price** is ever touched. Stock, cost and past invoices are never affected.

---

## 4. Brand rate slabs — automatic purchase rates

**This feature is switched OFF when the update is installed.** Set it up first, then turn it
on; until then, purchase entry behaves exactly as it does today.

### 4.1 Setting up a brand's slabs

**Sidebar → Menus → Product Management → Brands →** press **Slabs** on the brand's row.

1. Under **Quantity slabs**, add your quantity bands. A typical setup is two:

   | Label | From quantity | Up to (exclusive) |
   |---|---|---|
   | `Under 50` | `0` | `50` |
   | `50 and above` | `50` | *leave blank* |

   **Leave the last one's "Up to" blank** — blank means "and above", with no upper limit.

2. Press **Add slab** for each.

> **"Up to (exclusive)" explained:** a band that runs up to 50 covers quantities *below* 50.
> Quantity 50 itself falls into the next band. This is deliberate, so the two bands meet
> exactly with no gap and no overlap. The system will refuse to save bands that overlap.

3. Under **Rates per product**, type the rate for each product in each band. **Leave a box
   empty** if that product has no special rate at that quantity — the system will then
   simply not suggest anything.
4. Press **Save rates**.

### 4.2 Turning it on

**Sidebar → Settings →** tick
**"Slab purchase rates — suggest a brand's rate from the quantity entered"** → **Save**.

### 4.3 Using it

While adding a line to a purchase invoice, type the quantity. The **Unit price** fills in
automatically and a small note appears saying which slab it came from, e.g.
*"Auto-filled from slab "Under 50" — editable."*

> **It is only a suggestion.** You can always type over the price. Once you type your own
> price, the system stops overwriting it and just tells you what the slab rate would have
> been. Invoices you have already saved are never changed.

To stop using slabs, untick the Settings box. Your slab setup is kept, not deleted.

---

## 5. Customer Passbook — print and send on WhatsApp

Open any customer, supplier or salesman and go to their **Passbook** (also reachable from
the Passbook column on the Due List).

### 5.1 Printing a statement

1. Set the **From** and **To** dates, or use the **This year** / **Lifetime** shortcuts.
2. Press **Print / PDF**. A clean statement of account opens with your business header,
   opening balance, every transaction and the closing balance.
3. Ctrl+P → **Save as PDF** to keep a file.

### 5.2 Sending it on WhatsApp

1. Press the green **WhatsApp** button.
2. WhatsApp opens with a short summary already typed — the period, opening balance, total
   debit and credit, and the closing balance.
3. Attach the PDF you saved above if you want to send the full line-by-line statement.
4. Press send.

> **The WhatsApp button only appears when the customer has a phone number saved.** If it is
> missing, add the number on the customer's form first. This is deliberate — it prevents a
> customer's financial statement being sent to a wrong or half-entered number.

---

## 6. Finding products faster

**Sidebar → Menus → Product Management → Products**

The filter bar has three new controls:

| Filter | Use it to |
|---|---|
| **Category** | Narrow to one category (as well as the existing Brand and Sub-category) |
| **Stock** | Show only *In stock*, *Low stock*, or *Out of stock* items |
| **Selling price from / to** | Show only products in a price range — fill either box or both |

The **search box** now also matches the product **description** and the **category name**, on
top of name, HSN code, brand and sub-category.

All filters combine. For example: Category *Paint* + Stock *Low stock* answers "what paint do
I need to reorder?" in one step.

---

## 7. Setting commission for a single sale

While building a **Sales Invoice**, each line now has a **Comm %** box (called
**Commission % (this sale)** when editing an existing line).

| What you enter | What happens |
|---|---|
| **Leave blank** | The salesman's own standard rate is used — same as before |
| **A number, e.g. `4`** | That rate is used for this line only |
| **`0`** | No commission on this line at all |

Blank and `0` are genuinely different: blank means *"use their usual rate"*, while `0` means
*"no commission on this one"*.

**Notes**
- You must pick a **Salesman** on the line before you can set a rate for it.
- The rate is locked in when you **finalize** the invoice. Changing a salesman's standard
  rate later does not alter invoices already finalized.
- If the goods come back, the return reverses commission at **the same rate that was
  charged** — so a fully returned line nets back to zero automatically.

---

## 8. Phone numbers are now optional

On the **Customer** and **Supplier** forms, the Phone field has changed:

- It is **no longer required** — you can save a customer without one.
- It accepts **any format** — `033-2345 6789`, a second number, or a note like
  *"shop landline"*. The old "must be a 10-digit mobile" rule is gone.
- **If you do enter a number, it must still be unique.** Two customers cannot share the same
  number — that is what stops the same customer being created twice.

> **One trade-off to know:** without a phone number, the **WhatsApp** button on that
> customer's passbook will not appear (see 5.2). Add a number when you want to send them
> statements.

Staff logins, salesmen and employees still require a proper phone number. Only customers and
suppliers changed.

---

## Quick reference

| I want to… | Go to |
|---|---|
| See who owes me money | Reports → Ledgers → **Due List**, Customers tab |
| Separate old dues from recent ones | Due List → set **Default term** per customer |
| Know what I owe my salesmen | Due List → **Salesmen** tab → *Commission* column |
| Check a salesman's returns | Reports → Ledgers → **Returned Salesman A/c** |
| Raise all paint prices by 5% | Product Management → **Rate Management** |
| Undo a price change I just made | Rate Management → **Revert** on that batch |
| Send a customer their statement | Customer → **Passbook** → **Print / PDF**, then **WhatsApp** |
| See what needs reordering | Products → **Stock** = *Low stock* |
| Pay one sale at a different commission | Sales Invoice line → **Comm %** |
| Add a customer without a phone number | Just leave Phone blank |

---

## If something looks wrong

1. **Due List total does not match the Balance Sheet.** They are built from the same figures
   and should agree. If they do not, run **System → Integrity Check** and send the result to
   your administrator.
2. **A new menu item is missing.** Your role may not have been granted it yet. An
   administrator can add it under **Role Permissions**.
3. **The purchase price is not auto-filling.** Check that *Slab purchase rates* is ticked in
   **Settings**, and that the product has a rate saved for that quantity band.
4. **Revert on a rate change is blocked.** That is intended — one of the products was
   re-priced afterwards. The message names which ones.

# GP ENTERPRISE IMS — User Manual

Covers all eleven change requests (CR-1 … CR-11). Written for the people using the system
day to day, not for whoever installs it.

Wherever you see **Sidebar → Group → Item**, that is the menu path down the left-hand side.

---

## Coverage at a glance

| CR | What was asked for | Status | Section |
|---|---|---|---|
| CR-1 | Purchase entry brand-wise, with rates by under/above quantity | Done | [§7](#7-purchase-entry-and-brand-rate-slabs) |
| CR-2 | Due list for Customers & Salesmen — Commission / Cash / Total | Done | [§1](#1-due-list--who-owes-you-money) |
| CR-3 | Due list split Short-term / Long-term | Done | [§1.2](#12-marking-dues-short-term-or-long-term) |
| CR-4 | Rate changes category- and subcategory-wise | Done | [§4](#4-rate-management--changing-many-selling-prices-at-once) |
| CR-5 | Customer passbook with Print and WhatsApp | Done | [§3](#3-customer-passbook--print-and-send-on-whatsapp) |
| CR-6 | Commission per sale + Returned Salesman Account | Done | [§5](#5-setting-commission-for-a-single-sale), [§2](#2-returned-salesman-account) |
| CR-7 | Round Off on Sale and Purchase | Already in the system | [§8](#8-round-off) |
| CR-8 | Rebate and Discount on Sale and Purchase | Discount: done both sides. Rebate: purchase side only — see note | [§9](#9-discounts-and-rebates) |
| CR-9 | Flexible product searching | Done | [§6](#6-finding-products-faster) |
| CR-10 | Products without GST details; free-text phone | Done | [§10](#10-adding-products-and-customers-with-fewer-details) |
| CR-11 | Due List export as PDF | Done | [§1.4](#14-printing-or-exporting) |

> **One item is not included.** CR-8 asks for "Rebate and Discount … on both Sale and
> Purchase". **Discounts** work fully on both sides. **Rebates** exist only on the purchase
> side — money a brand or supplier gives *you*. There is no way to record a rebate you give
> *a customer* for hitting a target. If your business does that, it still needs to be built;
> see §9.3.

> **About "Print / PDF" buttons.** They open a clean printable page. To get a PDF file, press
> **Ctrl+P** (⌘+P on Mac) and choose **"Save as PDF"** instead of a physical printer. That
> file can then be emailed or attached in WhatsApp.

---

## 1. Due List — who owes you money

**Sidebar → Reports → Ledgers → Due List**

This screen is new. It has two tabs.

### 1.1 The Customers tab

Every customer who currently owes money, largest first.

| Column | Meaning |
|---|---|
| **Bills** | How many unpaid bills make up the amount |
| **Short-term** / **Long-term** / **Untagged** | The split you control — see 1.2 |
| **Total Due** | What they owe in total |
| **Oldest** | Date of their oldest unpaid bill — the ageing warning sign |
| **Default term** | The customer's usual term — see 1.2 |
| **Passbook** | Opens their full statement |

**To use it**
1. Type a name or phone in **Search**, or leave it blank for everyone.
2. Use **Due term** to show only *Short-term*, *Long-term* or *Untagged*.
3. Press **Apply**.

> Customers holding an unused advance show a green note under their name. That advance is
> **not** subtracted from Total Due — it is deliberately kept separate so this figure keeps
> matching the Balance Sheet.

### 1.2 Marking dues Short-term or Long-term

The system does not guess this. **You decide**, in one of two ways.

**Option A — set it once per customer (fastest).** In the **Default term** column choose
*Short-term* or *Long-term*. It saves immediately and covers all that customer's bills you
have not marked individually.

**Option B — mark one particular bill.** Use this when a single bill differs from the
customer's normal terms. A bill's own setting always beats the customer default.

**"Untagged"** means nobody has decided yet. Untagged money is still counted in **Total
Due** — it simply is not attributed to either bucket. Working that column down to zero is a
good habit.

### 1.3 The Salesmen tab

Salesmen can owe money *and* be owed money, so both are shown.

| Column | Meaning |
|---|---|
| **Commission** | What **you owe them** — earned, minus returns, minus what you already paid out |
| **Cash** | What **they owe you** on their own customer account |
| **Total** | Commission minus Cash |
| **Direction** | The plain answer: *we owe* / *owes us* / *settled* |

A positive **Total** means you owe them; a negative one (in red) means they owe you. The
**Direction** column says which, so you never have to work it out from the sign.

### 1.4 Printing or exporting

- **Print / PDF** — a clean statement with your business header. Ctrl+P → Save as PDF.
- **Export CSV** — opens in Excel.

Both follow whatever tab, search and filter you have on screen — so filter first, then print.

---

## 2. Returned Salesman Account

**Sidebar → Reports → Ledgers → Returned Salesman A/c**

Goods that came back against sales a salesman was credited for, and the commission reversed
with them. Useful for spotting an unusual level of returns.

1. Set **From** and **To** (defaults to the current financial year), then **Apply**.
2. The table shows each salesman with **Returns**, **Qty**, **Value**, **Cost** and
   **Commission reversed**.
3. Click the list icon under **Detail** for the individual items — return number, original
   invoice, customer, quantity, and commission reversed on each.

Opening one salesman's detail also shows what they *earned* in the same period, so a
reversal can be judged in proportion rather than in isolation.

> These reversals already appear in the salesman's Passbook. This screen breaks them out for
> review; it does not charge them twice.

---

## 3. Customer Passbook — print and send on WhatsApp

Open any customer, supplier or salesman and go to their **Passbook** (also reachable from the
Passbook column on the Due List).

### 3.1 Printing a statement

1. Set **From** and **To**, or use the **This year** / **Lifetime** shortcuts.
2. Press **Print / PDF**. A statement of account opens with your business header, opening
   balance, every transaction, and the closing balance.
3. Ctrl+P → **Save as PDF** to keep a file.

### 3.2 Sending it on WhatsApp

1. Press the green **WhatsApp** button.
2. WhatsApp opens with a summary already typed — period, opening balance, total debit and
   credit, closing balance.
3. Attach the PDF from 3.1 if you want to send the full line-by-line statement.
4. Send.

> **The WhatsApp button only appears when the customer has a phone number saved.** If it is
> missing, add the number on their form first. This is deliberate — it prevents a customer's
> financial statement going to a wrong or half-entered number.

---

## 4. Rate Management — changing many selling prices at once

**Sidebar → Menus → Product Management → Rate Management**

Change a whole category, sub-category or brand together instead of editing products one by
one. **Nothing is saved until you review a preview and confirm.**

### 4.1 Making a change

1. Narrow it down with **Category**, **Sub-category** and **Brand**. Leave a box on "All" to
   not narrow by it.
2. Choose a **Change type**:
   - **By percentage (%)** — `5` raises by 5%, `-5` reduces by 5%
   - **By flat amount (₹)** — `20` adds ₹20, `-20` takes ₹20 off
   - **Set to fixed price (₹)** — sets every matching product to the same price
3. Enter the **Value**. *Negative reduces the price.*
4. Tick **Round to nearest rupee** for tidy prices.
5. Press **Preview changes**.

### 4.2 Reading the preview

You get the exact list that would change, with **Current**, **New** and the **Change** for
each.

> **Watch the Margin column.** If a new price falls below what the stock cost you, it is
> shown in red as *(below cost)*. Check those before confirming.

Some products may be listed as **skipped** — click that line for the reason: either *"no
change"* (new price equals old) or *"would go below zero"*.

### 4.3 Applying and undoing

1. Add a **Remarks** note — e.g. *"April price revision from Berger"*. Worth doing; it is
   what you will read months later.
2. Press **Apply to N product(s)** and confirm.

Every change is listed under **Recent rate changes**. To undo one, press **Revert** on its
row — every product in that batch goes back to its previous price.

> **If Revert refuses:** one or more of those products has been re-priced since. Rather than
> wiping out that newer, deliberate price, the system stops and names them. Fix those
> individually instead.

Only the **selling price** is ever touched. Stock, cost and past invoices are unaffected.

---

## 5. Setting commission for a single sale

While building a **Sales Invoice**, each line has a **Comm %** box (**Commission % (this
sale)** when editing an existing line).

| What you enter | What happens |
|---|---|
| **Leave blank** | The salesman's own standard rate — same as before |
| **A number, e.g. `4`** | That rate, for this line only |
| **`0`** | No commission on this line at all |

Blank and `0` are genuinely different: blank means *"use their usual rate"*, `0` means *"no
commission on this one"*.

**Notes**
- Pick a **Salesman** on the line before setting a rate for it.
- The rate is locked in when you **finalize**. Changing a salesman's standard rate later does
  not alter invoices already finalized.
- If goods come back, the return reverses commission at **the same rate that was charged** —
  a fully returned line nets back to zero automatically.

---

## 6. Finding products faster

**Sidebar → Menus → Product Management → Products**

Three new filters:

| Filter | Use it to |
|---|---|
| **Category** | Narrow to one category (alongside the existing Brand and Sub-category) |
| **Stock** | Show only *In stock*, *Low stock* or *Out of stock* |
| **Selling price from / to** | Show a price range — fill either box or both |

The **search box** now also matches the product **description** and the **category name**, on
top of name, HSN code, brand and sub-category.

Filters combine. Category *Paint* + Stock *Low stock* answers "what paint do I need to
reorder?" in one step.

---

## 7. Purchase entry and brand rate slabs

### 7.1 Purchases are already brand-wise

Every purchase invoice belongs to **one brand**, chosen in the header. Once you add the first
line the brand locks, and the product list only offers that brand's products. This already
worked and has not changed.

### 7.2 Automatic rates by quantity — set-up

**This is switched OFF when the update is installed.** Set it up first, then turn it on;
until then purchase entry behaves exactly as today.

**Sidebar → Menus → Product Management → Brands →** press **Slabs** on the brand's row.

1. Under **Quantity slabs**, add your bands. A typical setup is two:

   | Label | From quantity | Up to (exclusive) |
   |---|---|---|
   | `Under 50` | `0` | `50` |
   | `50 and above` | `50` | *leave blank* |

   **Leave the last one's "Up to" blank** — blank means "and above", no upper limit.

2. Press **Add slab** for each.

> **"Up to (exclusive)" explained:** a band running up to 50 covers quantities *below* 50.
> Quantity 50 itself falls into the next band. This is deliberate, so bands meet exactly with
> no gap and no overlap. The system refuses to save bands that overlap.

3. Under **Rates per product**, type the rate for each product in each band. **Leave a box
   empty** if a product has no special rate at that quantity — nothing will be suggested.
4. Press **Save rates**.

### 7.3 Turning it on and using it

**Sidebar → Settings →** tick **"Slab purchase rates — suggest a brand's rate from the
quantity entered"** → **Save**.

Now, while adding a purchase line, type the quantity. The **Unit price** fills in
automatically with a note saying which slab it came from — e.g. *"Auto-filled from slab
"Under 50" — editable."*

> **It is only a suggestion.** You can always type over it. Once you do, the system stops
> overwriting and just tells you what the slab rate would have been. Saved invoices are never
> changed. Unticking the Settings box stops the suggestions and keeps your slab setup.

---

## 8. Round Off

**Already in the system — this section is here for completeness.**

**Sidebar → Settings →** the switch **"Round invoice grand total to nearest rupee"**.

When on, the grand total of an invoice is rounded to the nearest rupee and the adjustment is
shown as its own **Round Off** line on the invoice and on the printed bill, so the customer
can see it.

It applies to all four document types: **Sales Invoices, Purchase Invoices, Sales Returns and
Purchase Returns.**

---

## 9. Discounts and rebates

### 9.1 Discounts — two levels, both sides

Available on **both** Sales and Purchase invoices.

**Per line — the `AddSub %` box.** A signed percentage that adjusts that line:
- **Negative** (e.g. `-10`) takes 10% off the line — a discount.
- **Positive** (e.g. `5`) adds 5% to the line.

**Whole invoice — the `− Final Discount (₹)` box in the footer.** A flat rupee amount taken
off the invoice total. Typical use: *"Total 3,150 — final discount 50 — pay 3,100."*

You can use either, or both together.

### 9.2 Rebates from your brands and suppliers

**Sidebar → Menus → Brand Rebates → Record Rebate**

For money or goods a brand gives *you* when you hit a target. A rebate is always backed by
the brand's credit note, so the credit note number and date are required.

Four ways to record it:

| Mode | Use when |
|---|---|
| **Cash Back (money in)** | The brand actually pays you. Goes into the bank or drawer you choose. |
| **Reduce Payable (offset future bills)** | The credit note is set against bills you still owe them. |
| **Cost Reduction (recompute landed cost)** | The rebate lowers what your on-hand stock effectively cost. |
| **Free Stock (extra units)** | They send extra units instead of money. |

Recorded rebates appear under **All Rebates** and can be voided if entered by mistake.

### 9.3 Rebates you give a customer — not available

CR-8 asked for rebates on both sides. Only the purchase side exists: rebates you *receive*.
There is currently **no screen for a rebate you give a customer** for hitting a target.

If you need that, use one of these in the meantime, and tell your administrator so it can be
built properly:
- a **Final Discount** on the relevant invoice, or
- a **Sales Return / credit note** to reduce what the customer owes.

Neither is a true rebate record — they will not be reportable as rebates — but both correctly
reduce the customer's balance.

---

## 10. Adding products and customers with fewer details

### 10.1 Products without GST details

A product can be saved without an **HSN code** and with **Tax Rate** left at `0`. Neither is
required.

For a bill with no GST at all, set the invoice's **Tax treatment** to **No tax**, or its
**Bill type** to **Bill of Supply**.

### 10.2 Phone numbers are now optional

On the **Customer** and **Supplier** forms:

- Phone is **no longer required** — you can save without one.
- It accepts **any format** — `033-2345 6789`, a second number, or a note like *"shop
  landline"*. The old "must be a 10-digit mobile" rule is gone.
- **If you do enter a number it must still be unique.** Two customers cannot share one — that
  is what stops the same customer being created twice.

> **Trade-off:** without a phone number the **WhatsApp** button on that customer's passbook
> will not appear (see 3.2). Add a number when you want to send them statements.

Staff logins, salesmen and employees still require a proper phone number. Only customers and
suppliers changed.

---

## Quick reference

| I want to… | Go to |
|---|---|
| See who owes me money | Reports → Ledgers → **Due List**, Customers tab |
| Separate old dues from recent ones | Due List → set **Default term** per customer |
| Know what I owe my salesmen | Due List → **Salesmen** tab → *Commission* |
| Check a salesman's returns | Reports → Ledgers → **Returned Salesman A/c** |
| Raise all paint prices by 5% | Product Management → **Rate Management** |
| Undo a price change I just made | Rate Management → **Revert** on that batch |
| Send a customer their statement | Customer → **Passbook** → **Print / PDF**, then **WhatsApp** |
| See what needs reordering | Products → **Stock** = *Low stock* |
| Pay one sale at a different commission | Sales Invoice line → **Comm %** |
| Give a discount on one line | Sales/Purchase line → **AddSub %**, negative number |
| Knock a flat amount off a bill | Invoice footer → **− Final Discount (₹)** |
| Record a rebate from a brand | Menus → **Brand Rebates** → Record Rebate |
| Add a customer without a phone number | Just leave Phone blank |
| Auto-fill purchase rates by quantity | Brands → **Slabs**, then tick it in **Settings** |

---

## If something looks wrong

1. **Due List total does not match the Balance Sheet.** They are built from the same figures
   and should agree. Run **System → Integrity Check** and send the result to your
   administrator.
2. **A new menu item is missing.** Your role may not have it granted yet. An administrator
   can add it under **Role Permissions**.
3. **Purchase price is not auto-filling.** Check *Slab purchase rates* is ticked in
   **Settings**, and that the product has a rate saved for that quantity band.
4. **Revert on a rate change is blocked.** Intended — a product was re-priced afterwards. The
   message names which ones.
5. **The WhatsApp button is missing.** That customer has no phone number saved.

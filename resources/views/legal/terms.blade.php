{{--
    Terms of Service: the one text shown on the website (/terms), in the buyer app (GET /legal/terms)
    and from the rider app. Numbers come from the same settings the system enforces, so the terms
    never drift from what the platform does. Bump config('legal.version') when the wording changes.
--}}
@php
    $p = config('pickrush.policies');
    $commission = rtrim(rtrim(number_format((float) config('app.platform_commission_rate'), 2), '0'), '.');
    $deliveryCommission = rtrim(rtrim(number_format((float) config('app.delivery_commission_rate'), 2), '0'), '.');
    $shipHours = \App\Models\Order::SHIP_WITHIN_HOURS;
    $autoConfirm = \App\Services\Orders\OrderReceipt::AUTO_CONFIRM_DAYS;
    $strikeDays = \App\Services\Logistics\SellerStrikes::LIFETIME_DAYS;
    $strikeHold = \App\Services\Logistics\SellerStrikes::HOLD_AT;
    $vacationDays = \App\Models\User::MAX_VACATION_DAYS_PER_MONTH;
    $contact = route('contact');
@endphp

<p>These Terms of Service ("Terms") are an agreement between you and PickSell, the operator of the PickSell online marketplace and of Pickrush, its delivery network (together, "PickSell", "we", "us"). They apply to the PickSell website, the PickSell buyer app, the Pickrush rider app, the Pickrush partner portal and every service we offer through them (the "Platform").</p>
<p><strong>By creating an account, ticking "I agree", or using the Platform, you accept these Terms and our <a href="{{ route('privacy') }}">Privacy Policy</a>.</strong> If you do not agree, do not use the Platform. The version you agreed to and the time you agreed are recorded with your account.</p>

<h2>1. Who does what on PickSell</h2>
<ul>
    <li><strong>PickSell is a marketplace, not the seller.</strong> Sellers list and sell their own goods. The contract of sale is between the buyer and the seller. PickSell provides the Platform, handles orders and payments in the ways described here, and enforces these Terms.</li>
    <li><strong>Buyers</strong> browse, order, pay and receive goods.</li>
    <li><strong>Sellers</strong> (individuals, sole proprietors and corporations) list goods, prepare orders and hand them to a courier.</li>
    <li><strong>Logistics partners</strong> are independent delivery companies registered on Pickrush. They run hubs and stations and carry parcels between sellers and buyers.</li>
    <li><strong>Riders and truck drivers</strong> collect and deliver parcels for a logistics partner. Riders apply through the rider app and are approved by their station; truck drivers are registered by their hub.</li>
    <li><strong>Hub and station staff</strong> work for a logistics partner and use the partner portal.</li>
    <li><strong>PickSell administrators</strong> review registrations, listings, reports, disputes and payouts.</li>
</ul>

<h2>2. Accounts</h2>
<ul>
    <li>You must be at least 18 years old and legally able to enter into contracts in the Philippines.</li>
    <li>Give true, complete and current information, including the government ID and documents we ask for. Keep it up to date.</li>
    <li>We verify your email with a one-time code and review every registration. We may approve or disapprove it, ask for clearer documents, or later suspend or deactivate an account that breaks these Terms or the law.</li>
    <li>One person or business, one account per role. Do not create another account to get around a suspension, a strike or a limit.</li>
    <li>Keep your password private. You are responsible for everything done through your account until you tell us it was compromised.</li>
</ul>

<h2>3. Buying</h2>
<ul>
    <li><strong>Prices and listings.</strong> The seller sets each price, discount and description, and is responsible for their accuracy. The price and shipping fee shown at checkout are the ones you pay.</li>
    <li><strong>Payment.</strong> Orders are paid by <strong>Cash on Delivery</strong>: you pay the rider, in cash, the amount shown on the order when it is delivered. Pay only that amount, and only to the rider delivering your PickSell parcel.</li>
    <li><strong>Shipping fee.</strong> It is worked out from the parcel's packed weight and the distance between the seller and your address, and shown before you place the order.</li>
    <li><strong>Vouchers</strong> apply only under their stated conditions (minimum spend, dates, limits) and have no cash value.</li>
    <li><strong>Cancelling.</strong> You can cancel an order while it is placed or confirmed, before the seller starts preparing it. After that, the order goes ahead and you can use the return process instead.</li>
    <li><strong>Receiving.</strong> Give a delivery address and pin couriers can reach, and be available to receive. After delivery, confirm that you received the order; if you do not, it is confirmed for you {{ $autoConfirm }} days after delivery.</li>
    <li><strong>Refusing a parcel.</strong> Do not order goods you do not intend to accept. Repeated refusals or unreachable addresses may lead us to limit your account.</li>
</ul>

<h2>4. Returns and refunds</h2>
<ul>
    <li>You may ask for a return within <strong>{{ $p['return_window_days'] }} days after delivery</strong> if the item is damaged, wrong, the wrong size, or not as described. Give the details and photos the request form asks for.</li>
    <li>The seller reviews the request. If it is accepted, you send the item back as instructed; once the seller receives and inspects it, the refund is processed as shown on the return.</li>
    <li>If you and the seller do not agree, either of you can ask PickSell to review it. We decide based on the order, the photos, the chat and the delivery records, and our decision on the Platform is final, without affecting any right you have under the law.</li>
    <li>Your rights under the Consumer Act of the Philippines (Republic Act No. 7394) and other laws are not reduced by these Terms.</li>
</ul>

<h2>5. Selling</h2>
<ul>
    <li><strong>What you may sell.</strong> Only goods you own or may lawfully sell, that are genuine, safe and as described, in the category you registered for. You are responsible for the permits, registrations, taxes and receipts your business needs.</li>
    <li><strong>Listings.</strong> Use your own photos and accurate details, including a correct packed weight (shipping is priced on it). Listings may be reviewed, held or archived by PickSell; one archived by an administrator can only be restored by one.</li>
    <li><strong>Options.</strong> If you sell in options (such as color and size), each combination's stock and price must be accurate.</li>
    <li><strong>Fulfilling orders.</strong> Confirm and prepare orders promptly, pack them safely, and hand each parcel to the courier within {{ $shipHours }} hours of preparing it, or within the days you set for a pre-order. An order not handed over in time is cancelled automatically.</li>
    <li><strong>Vacation mode.</strong> You may pause orders for up to {{ $vacationDays }} days a month. Your listings stay visible but cannot be ordered.</li>
    <li><strong>Fees.</strong> PickSell charges a commission of <strong>{{ $commission }}%</strong> on completed orders (or the rate shown in your seller portal, which prevails). Commission rates may change with notice; a change does not affect orders already placed.</li>
    <li><strong>Seller payouts</strong> for completed orders are settled as shown in your seller portal, less commission and any amounts you owe under these Terms.</li>
</ul>

<h2>6. Deliveries, failed deliveries and returned parcels</h2>
<ul>
    <li>Parcels are collected and delivered by Pickrush logistics partners and their riders. Delivery dates are estimates; delays caused by weather, traffic, address problems or events outside anyone's control do not make PickSell liable.</li>
    <li>A rider tries to deliver a parcel up to <strong>{{ $p['max_delivery_attempts'] }} times</strong>. After that, it is returned to the seller.</li>
    <li>For a returned parcel the seller pays the logistics partner a return fee equal to the parcel's original shipping fee, in cash to the rider when it is handed back. PickSell takes no commission on it.</li>
    <li>If the seller does not take the parcel back, it is held at the station for {{ $p['return_hold_days'] }} days, during which the seller can ask for another hand-back or collect it. A return not taken back within <strong>{{ $p['return_abandon_days'] }} days</strong> is treated as abandoned: the seller gives up the parcel, and the logistics partner may keep, sell, donate or dispose of it to recover its costs.</li>
    <li>PickSell reviews reported problems with the seller. A seller found at fault receives a strike that lapses after {{ $strikeDays }} days. With {{ $strikeHold }} active strikes the shop stops taking new orders until PickSell resolves it.</li>
    <li>Report a damaged, missing or wrong item through the order's "Report a problem" as soon as you notice it. Claims against a logistics partner are handled through PickSell's issue process.</li>
</ul>

<h2>7. Keeping deals on PickSell</h2>
<ul>
    <li>Buyers and sellers who meet on PickSell must order, pay and deliver through PickSell. Asking for, offering or accepting payment, contact details or delivery outside the Platform is not allowed.</li>
    <li>To protect users, phone numbers, emails, links and similar contact details are automatically hidden in buyer–seller chat, and listings that contain them are held for review. Attempts are flagged for PickSell to review.</li>
    <li>Possible results include a warning, a strike, a compliance case, or suspension. Every penalty is decided by a PickSell administrator, not automatically.</li>
</ul>

<h2>8. Logistics partners, riders and drivers</h2>
<ul>
    <li><strong>Logistics partners</strong> are independent businesses, not PickSell's employees or agents. They are responsible for their staff, riders, drivers, vehicles, permits, insurance and the parcels in their care, and for following the delivery, cash and return rules of the partner portal.</li>
    <li><strong>Riders</strong> must keep their driver's license and vehicle OR/CR valid and renew them through the app before they expire; a rider with an expired document cannot take parcels. Riders follow the cash-on-delivery limits, parcel caps and vehicle rules set by their hub.</li>
    <li><strong>Cash collected on delivery</strong> belongs to the order and must be remitted in full as the app instructs. Keeping, delaying or mishandling COD cash is a serious breach and may be reported to the authorities.</li>
    <li><strong>Rider pay</strong> is credited to the rider's wallet and settled in a weekly payslip (Monday to Sunday). The station manager releases it, or it releases itself {{ \App\Pickrush\Services\Rider\RiderPayroll::AUTO_RELEASE_AFTER_DAYS }} days after the week ends. Deductions can be disputed in the app.</li>
    <li>PickSell takes a commission of {{ $deliveryCommission }}% of each delivery fee after the rider's pay; the logistics partner keeps the rest.</li>
    <li>Use buyer and seller details shown in the app only to deliver or collect that parcel. Do not copy, share or use them for anything else.</li>
</ul>

<h2>9. Things you must not do</h2>
<ul>
    <li>List or sell illegal, counterfeit, stolen, unsafe, recalled or prohibited goods, or goods that infringe someone's rights.</li>
    <li>Give false information, impersonate anyone, or use someone else's account, ID or documents.</li>
    <li>Place fake orders, write fake or paid reviews, or manipulate ratings, vouchers or rankings.</li>
    <li>Harass, threaten, scam or discriminate against anyone, including riders and staff.</li>
    <li>Interfere with the Platform: hacking, scraping, overloading, reverse engineering, or getting around its limits and security.</li>
    <li>Use personal information you see on the Platform for anything other than the transaction it was shared for.</li>
</ul>

<h2>10. Reviews and content</h2>
<ul>
    <li>Reviews must be honest and about your own purchase. Sellers may reply. PickSell may hide reviews or content that break these Terms; reports are reviewed by an administrator.</li>
    <li>You keep ownership of what you upload (photos, descriptions, reviews), and you give PickSell a non-exclusive, royalty-free licence to host, show and use it to run and promote the Platform. You confirm you have the right to upload it.</li>
    <li>The PickSell and Pickrush names, logos and software belong to PickSell. Do not use them without permission.</li>
</ul>

<h2>11. Suspension, deactivation and account deletion</h2>
<ul>
    <li>We may limit, suspend or deactivate an account, hold a listing, or hold a payout while we look into a breach of these Terms, fraud, a safety risk, or a legal request. Where we can, we tell you why and how to respond.</li>
    <li>You can delete your account from your account page once you have no order or return in progress. Deleting it erases your personal details; records of past orders, payments and returns are kept, without your personal details, because the other party, the law and dispute handling still need them (see the <a href="{{ route('privacy') }}">Privacy Policy</a>).</li>
    <li>Obligations that exist when an account ends (such as unpaid fees, COD to remit, or a pending refund) continue after it ends.</li>
</ul>

<h2>12. Limits of our responsibility</h2>
<ul>
    <li>The Platform is provided "as is" and "as available". We work to keep it secure and running, but we do not promise it will be uninterrupted or error-free.</li>
    <li>PickSell is not responsible for the quality, safety, legality or description of goods sold by sellers, or for the acts of buyers, sellers, logistics partners, riders or drivers, except where the law makes us responsible or where the loss is caused by our own fault.</li>
    <li>To the extent the law allows, PickSell is not liable for indirect or consequential loss (such as lost profits or lost business), and our total liability to you for any claim is limited to the fees PickSell earned from the transaction concerned.</li>
    <li>Nothing in these Terms limits liability that cannot be limited under Philippine law, including for fraud, gross negligence or wilful misconduct.</li>
    <li>We are not liable for delays or failures caused by events beyond reasonable control, such as natural disasters, severe weather, power or network outages, government action, strikes or civil unrest.</li>
</ul>

<h2>13. Your responsibility to us</h2>
<p>If you break these Terms or the law and a claim is made against PickSell because of it, you agree to cover PickSell's resulting losses, costs and reasonable legal fees, to the extent the law allows.</p>

<h2>14. Disputes</h2>
<ul>
    <li>Tell us first through the <a href="{{ $contact }}">Contact page</a> or the in-app report and return tools. Most problems are settled there.</li>
    <li>If a dispute is not settled, you and PickSell will try to resolve it in good faith for 30 days before going to court. Consumers may also bring complaints to the Department of Trade and Industry.</li>
    <li>These Terms are governed by the laws of the Republic of the Philippines, including the Electronic Commerce Act (Republic Act No. 8792) and the Internet Transactions Act of 2023 (Republic Act No. 11967).</li>
</ul>

<h2>15. Changes to these Terms</h2>
<p>We may update these Terms when the Platform or the law changes. The version date at the top shows the current one. For important changes we will give notice on the Platform before they apply. Continuing to use the Platform after a change takes effect means you accept it.</p>

<h2>16. General</h2>
<ul>
    <li>If any part of these Terms is found invalid, the rest still applies.</li>
    <li>Not enforcing a part of these Terms right away does not mean we give it up.</li>
    <li>These Terms, the Privacy Policy and the rules shown in the Platform for a feature are the whole agreement between you and PickSell about the Platform.</li>
</ul>

<h2>17. Contact</h2>
<p>Questions about these Terms? Reach us through the <a href="{{ $contact }}">Contact page</a>.</p>

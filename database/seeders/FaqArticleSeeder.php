<?php

namespace Database\Seeders;

use App\Models\FaqArticle;
use Illuminate\Database\Seeder;

/** Knowledge base / FAQ content (PRD §22) — safe to run in every environment. */
class FaqArticleSeeder extends Seeder
{
    public function run(): void
    {
        $articles = [
            'Getting Started' => [
                ['How do I create a JolaxPay account?', 'Tap "Create Account" and enter your full name, phone number, email, and a password — no BVN or NIN needed. If someone referred you, enter their referral code on the same screen to link your account to them.'],
                ['Is JolaxPay free to use?', 'Creating an account and funding your wallet by bank transfer is free. Card top-ups and purchases carry a small convenience fee, which is always shown before you confirm payment — never a surprise deduction.'],
            ],
            'Wallet & Payments' => [
                ['How do I fund my wallet?', 'Go to Wallet from the Home screen and tap "Top Up". You can fund by card via our secure Paystack checkout. Once payment is confirmed, your balance updates automatically — this can take a few seconds.'],
                ['My card payment succeeded but my wallet wasn\'t credited. What do I do?', 'This is usually just a brief delay while we confirm the payment with our processor. Pull down to refresh the Wallet screen after a minute. If your balance still hasn\'t updated after a few minutes, contact Support from the app with your payment reference.'],
                ['Can I send money to another JolaxPay user?', 'Yes. Every wallet has a unique wallet address (starts with JLX). Go to Wallet > Transfer, enter the recipient\'s wallet address and an amount, and it moves instantly — no fees.'],
                ['How do I withdraw from my wallet to my bank account?', 'Go to Wallet > Withdraw, choose your bank from the list, enter your account number to verify the account name, then enter an amount. Withdrawals are processed by our payment partner and usually complete within minutes.'],
                ['Are my payment details safe?', 'Yes. Card payments are handled entirely by Paystack\'s secure checkout — JolaxPay never sees or stores your card number.'],
            ],
            'Buying Electricity & Bills' => [
                ['How do I buy electricity units?', 'Tap "Buy Token" on Home, choose a saved meter (or add a new one), enter an amount, and confirm. Your prepaid token appears on the purchase confirmation screen and is also saved to your transaction history.'],
                ['I bought a token but power hasn\'t come back on. What now?', 'First double-check the token was entered correctly on your meter. If power still isn\'t restored, open the transaction from History and use the "Was your power restored?" prompt to tell us — this flags it for our team to follow up.'],
                ['Can I buy airtime, data, TV, or education pins?', 'Yes — tap "Pay Bills" on Home or open the Purchase tab and choose the service. You can select a saved contact or enter a new number, choose a plan if one applies, and pay from your wallet or a card.'],
                ['Can I save a meter or a phone number so I don\'t retype it?', 'Yes. Saved Meters and Saved Contacts (under your Profile) let you store meters and airtime/data recipients you use often, so you can pick them with one tap next time.'],
                ['What is Emergency Recharge?', 'It\'s a one-tap shortcut on Home for when your power is out — it pre-fills your favorite (or most recent) meter and your usual recharge amount so all you need to do is confirm.'],
                ['Can I schedule a recurring recharge?', 'Yes, under Scheduled Purchases in your Profile you can set up a weekly, biweekly, or monthly automatic recharge for a meter or bill.'],
                ['What are Meter Groups and Power Circle?', 'Meter Groups let you organize multiple meters (e.g. for a landlord or business with several properties) so you can top them up together. Power Circle lets you keep a list of people you regularly send power/airtime support to, each optionally linked to a meter.'],
            ],
            'Referrals & Rewards' => [
                ['How does the referral program work?', 'Share your referral code (found under Referrals in your Profile) with friends. When they sign up with your code and complete their first successful purchase, you automatically receive a wallet credit reward — no waiting on approval.'],
                ['Where do I find my referral code?', 'Go to Profile > Referrals. Your code is shown at the top with a "Share my code" button.'],
            ],
            'Account & Security' => [
                ['Why was I asked for a verification code on a large purchase?', 'For your protection, purchases above a certain amount require a one-time code sent to your phone before they go through. This helps prevent unauthorized use of your account.'],
                ['How do I see which devices are logged into my account?', 'Go to Profile > Active Sessions to see every device currently signed in, with the one you\'re using clearly marked. You can revoke any device you don\'t recognize.'],
                ['Can I control which notifications I receive?', 'Yes — go to Profile > Notifications to turn categories like wallet activity or referral rewards on or off individually.'],
                ['How do I get a receipt for a purchase or wallet transaction?', 'Open the transaction from History and tap "Download / Share receipt" for a PDF you can save or send.'],
            ],
            'Support' => [
                ['How do I contact support?', 'Go to Profile > Support and tap "New ticket" to describe your issue — you can optionally link it to a specific transaction. Our team replies in the same thread, and you\'ll get a notification when they do.'],
            ],
        ];

        $sortOrder = 0;
        foreach ($articles as $category => $items) {
            foreach ($items as [$question, $answer]) {
                FaqArticle::updateOrCreate(
                    ['category' => $category, 'question' => $question],
                    ['answer' => $answer, 'sort_order' => $sortOrder++, 'is_published' => true],
                );
            }
        }
    }
}

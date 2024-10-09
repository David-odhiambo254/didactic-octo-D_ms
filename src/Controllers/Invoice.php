<?php
namespace Simcify\Controllers;

use Simcify\Database;
use Simcify\Landa;
use Simcify\Auth;

class Invoice{

    /**
     * Get invoice view
     * 
     * @return \Pecee\Http\Response
     */
    public function get() {
        $user = Auth::user();
        $invoices = Database::table('invoices')->leftJoin('users','invoices.student','users.id')->where("invoices`.`branch", $user->branch)->orderBy('invoices.id', false)->get("`users.fname`", "`users.lname`", "`users.avatar`", "`users.email`","`invoices.id`", "`invoices.created_at`", "`invoices.amount`", "`invoices.amountpaid`", "`invoices.student`", "`invoices.reference`");
        return view('invoices', compact("invoices","user"));
    }

   /**
     * Add Payment invoice
     * 
     * @return null
     */
    public function addpayment() {
        $user = Auth::user();
        $invoice = Database::table('invoices')->where('id',input('invoice'))->first();
        $student = Database::table('users')->where('id',$invoice->student)->first();
        $data = array(
          'invoice'=>$invoice->id,
          'student'=>$invoice->student,
          'school'=>$invoice->school,
          'branch'=>$invoice->branch,
          'method'=>input('method'),
          'amount'=>input('amount'),
          'created_at'=>date('Y-m-d h:i:s', strtotime(input("payday")))
        );
        Database::table('payments')->insert($data);
        $data = array(
          'amountpaid'=>input('amount') + $invoice->amountpaid
        );
        Database::table('invoices')->where('id',$invoice->id)->update($data);
        $notification = 'You made a payment of <strong>'.money(input('amount')).'</strong>.';
        Landa::notify($notification, $invoice->student, "payment", "personal");
        $notification = 'A payment of <strong>'.money(input('amount')).'</strong> has been received from <strong>'.$student->fname.' '.$student->lname.'</strong>.';
        Landa::notify($notification, $user->id, "payment");

        return response()->json(responder("success", "Alright", "Payment successfully added.", "reload()"));
    }
    
    /**
     * Delete payment
     * 
     * @return Json
     */
    public function deletepayment() {
        $user = Auth::user();
        $payment = Database::table("payments")->where("id", input("paymentid"))->first();
        $invoice = Database::table('invoices')->where('id',$payment->invoice)->first();
        Database::table("payments")->where("id", input("paymentid"))->delete();
        $data = array(
          'amountpaid'=> $invoice->amount - $payment->amount
        );
        Database::table('invoices')->where('id',$invoice->id)->update($data);
        $notification = 'A payment record of <strong>'.money($payment->amount).'</strong> has been deleted from your account.';
        Landa::notify($notification, $invoice->student, "delete", "personal");
        $notification = 'A payment record of <strong>'.money($payment->amount).'</strong> has been deleted by <strong>'.$user->fname.' '.$user->lname.'</strong>.';
        Landa::notify($notification, $user->id, "delete");
        return response()->json(responder("success", "Payment Deleted", "Payment successfully deleted", "reload()"));
    }
    
    /**
     * View payments
     * 
     * @return Json
     */
    public function viewpayments() {
        $payments = Database::table("payments")->where("invoice", input("invoiceid"))->get();
        return view('extras/payments', compact("payments"));
      }
    
    /**
     * Update invoice
     * 
     * @return Json
     */
    public function update() {
        $data = array(
            "item" => escape(input("item")),
            "amount" => escape(input("amount"))
        );
        Database::table("invoices")->where("id", input("invoiceid"))->update($data);
        return response()->json(responder("success", "Alright", "Invoice successfully updated", "reload()"));
    }
    
    /**
     * Update invoice view
     * 
     * @return Json
     */
    public function updateview() {
        $invoice = Database::table("invoices")->where("id", input("invoiceid"))->first();
        return view('extras/updateinvoice', compact("invoice"));
    }
    
    /**
     * Delete invoice
     * 
     * @return Json
     */
    public function delete() {
        $user = Auth::user();
        $invoice = Database::table("invoices")->where("id", input("invoiceid"))->first();
        Database::table("invoices")->where("id", input("invoiceid"))->delete();
        $notification = 'Invoice #'.$invoice->reference.' of <strong>'.money($invoice->amount).'</strong> has been deleted from your account.';
        Landa::notify($notification, $invoice->student, "delete", "personal");
        $notification = 'Invoice #'.$invoice->reference.' of <strong>'.money($invoice->amount).'</strong>  has been deleted by <strong>'.$user->fname.' '.$user->lname.'</strong>.';
        Landa::notify($notification, $user->id, "delete");
        return response()->json(responder("success", "Invoice Deleted", "Invoice successfully deleted.", "reload()"));
    }


     /**
     * Download Invoice file
     * 
     * @return integer
     */
    public function download($invoiceid) {
      $invoice = Database::table('invoices')->where('id',$invoiceid)->first();
      $mpdf = new \Mpdf\Mpdf([
                      'tempDir' => config("app.storage")."mpdf",
                        'margin_top' => 0,
                        'margin_left' => 0,
                        'margin_right' => 0,
                        'mirrorMargins' => true
                    ]);
      $mpdf->WriteHTML(self::preview($invoiceid, "#fff"));
      $mpdf->Output("Invoice #".$invoice->reference.".pdf", 'D');
    }

    /**
     * Get invoice view
     * 
     * @return \Pecee\Http\Response
     */
    public function preview($invoiceid, $background = "#F8F8F8") {

        $invoice = Database::table('invoices')->where('id',$invoiceid)->first();
        $student = Database::table('users')->where('id',$invoice->student)->first();
        $school = Database::table('schools')->where('id',$invoice->school)->first();
        return view('invoicepreview', compact("invoice", "student", "school", "background"));

    }

}

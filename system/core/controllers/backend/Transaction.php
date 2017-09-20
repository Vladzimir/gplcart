<?php

/**
 * @package GPL Cart core
 * @author Iurii Makukh <gplcart.software@gmail.com>
 * @copyright Copyright (c) 2015, Iurii Makukh
 * @license https://www.gnu.org/licenses/gpl.html GNU/GPLv3
 */

namespace gplcart\core\controllers\backend;

use gplcart\core\models\Payment as PaymentModel,
    gplcart\core\models\Transaction as TransactionModel;
use gplcart\core\controllers\backend\Controller as BackendController;

/**
 * Handles incoming requests and outputs data related to order transactions
 */
class Transaction extends BackendController
{

    /**
     * Payment model instance
     * @var \gplcart\core\models\Payment $payment
     */
    protected $payment;

    /**
     * Transaction model instance
     * @var \gplcart\core\models\Transaction $transaction
     */
    protected $transaction;

    /**
     * @param PaymentModel $payment
     * @param TransactionModel $transaction
     */
    public function __construct(PaymentModel $payment,
            TransactionModel $transaction)
    {
        parent::__construct();

        $this->payment = $payment;
        $this->transaction = $transaction;
    }

    /**
     * Displays the transaction overview page
     */
    public function listTransaction()
    {
        $this->actionListTransaction();

        $this->setTitleListTransaction();
        $this->setBreadcrumbListTransaction();

        $this->setFilterListTransaction();
        $this->setTotalListTransaction();
        $this->setPagerLimit();

        $this->setData('transactions', $this->getListTransaction());
        $this->setData('payment_methods', $this->payment->getList());

        $this->outputListTransaction();
    }

    /**
     * Sets filter on the transaction overview page
     */
    protected function setFilterListTransaction()
    {
        $allowed = array('created', 'order_id', 'payment_method',
            'gateway_transaction_id');

        $this->setFilter($allowed);
    }

    /**
     * Sets a total transactions found for the given conditions
     */
    protected function setTotalListTransaction()
    {
        $query = $this->query_filter;
        $query['count'] = true;
        $this->total = (int) $this->transaction->getList($query);
    }

    /**
     * Applies an action to the selected transactions
     */
    protected function actionListTransaction()
    {
        list($selected, $action) = $this->getPostedAction();

        $deleted = 0;
        foreach ($selected as $id) {

            if ($action === 'delete' && $this->access('transaction_delete')) {
                $deleted += (int) $this->transaction->delete($id);
            }
        }

        if ($deleted > 0) {
            $message = $this->text('Deleted %num item(s)', array('%num' => $deleted));
            $this->setMessage($message, 'success');
        }
    }

    /**
     * Returns an array of transactions
     * @return array
     */
    protected function getListTransaction()
    {
        $query = $this->query_filter;
        $query['limit'] = $this->limit;

        return (array) $this->transaction->getList($query);
    }

    /**
     * Sets titles on the transaction overview page
     */
    protected function setTitleListTransaction()
    {
        $this->setTitle($this->text('Transactions'));
    }

    /**
     * Sets breadcrumbs on the transaction overview page
     */
    protected function setBreadcrumbListTransaction()
    {
        $this->setBreadcrumbHome();
    }

    /**
     * Render and output the transaction overview page
     */
    protected function outputListTransaction()
    {
        $this->output('sale/transaction/list');
    }

}

<?php
namespace verbb\abandonedcart\controllers;

use verbb\abandonedcart\AbandonedCart;
use verbb\abandonedcart\helpers\Duration;
use verbb\abandonedcart\models\CartSummary;
use verbb\abandonedcart\models\Email;

use Craft;

use Throwable;
use yii\web\NotFoundHttpException;
use yii\web\Response;

use craft\commerce\elements\Order;

/**
 * JSON endpoints behind the email panel on a sequence.
 */
class EmailsController extends BaseCpController
{
    // Public Methods
    // =========================================================================

    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requireAcceptsJson();
        $this->requirePermission(AbandonedCart::PERMISSION_MANAGE_SEQUENCES);

        return true;
    }

    public function actionGet(): Response
    {
        $email = $this->_getEmail((int)$this->request->getRequiredParam('id'));

        return $this->asJson(['email' => $this->_serialize($email)]);
    }

    public function actionSave(): Response
    {
        $this->requirePostRequest();

        $email = $this->_populate();

        if (!AbandonedCart::$plugin->getEmails()->saveEmail($email)) {
            return $this->asModelFailure($email, Craft::t('abandoned-cart', 'Couldn’t save email.'), 'email', ['errors' => $email->getErrors()]);
        }

        return $this->asSuccess(Craft::t('abandoned-cart', 'Email saved.'), ['email' => $this->_serialize($email)]);
    }

    public function actionDelete(): Response
    {
        $this->requirePostRequest();

        $email = $this->_getEmail((int)$this->request->getRequiredBodyParam('id'));
        AbandonedCart::$plugin->getEmails()->deleteEmail($email);

        return $this->asSuccess(Craft::t('abandoned-cart', 'Email deleted.'));
    }

    public function actionPreview(): Response
    {
        $this->requirePostRequest();

        $email = $this->_populate();
        $order = $this->_getOrder();
        $cart = $order ? CartSummary::fromOrder($order) : CartSummary::sample($email->getSequence()?->siteIds[0] ?? null);

        try {
            $rendered = AbandonedCart::$plugin->getEmailRenderer()->render($email, $cart, $order, ['recoverUrl' => '#', 'unsubscribeUrl' => '#'], true);
        } catch (Throwable $e) {
            return $this->asFailure(Craft::t('abandoned-cart', 'The template couldn’t be rendered: {error}', ['error' => $e->getMessage()]));
        }

        return $this->asJson([
            'subject' => $rendered->subject,
            'previewText' => $rendered->previewText,
            'html' => $rendered->html,
            'from' => Craft::$app->getProjectConfig()->get('email.fromName') ?: $cart->email,
        ]);
    }

    public function actionSendTest(): Response
    {
        $this->requirePostRequest();

        $email = $this->_populate();
        $to = trim((string)$this->request->getRequiredBodyParam('to'));

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return $this->asFailure(Craft::t('abandoned-cart', 'Enter a valid email address to send the test to.'));
        }

        try {
            $sent = AbandonedCart::$plugin->getAttempts()->sendTest($email, $to, $this->_getOrder());
        } catch (Throwable $e) {
            return $this->asFailure(Craft::t('abandoned-cart', 'The test couldn’t be sent: {error}', ['error' => $e->getMessage()]));
        }

        return $sent
            ? $this->asSuccess(Craft::t('abandoned-cart', 'Test sent to {email}.', ['email' => $to]))
            : $this->asFailure(Craft::t('abandoned-cart', 'The mailer couldn’t send the test. Check Craft’s email settings.'));
    }


    // Private Methods
    // =========================================================================

    private function _populate(): Email
    {
        $id = (int)$this->request->getBodyParam('id');
        $email = $id ? $this->_getEmail($id) : new Email(['sequenceId' => (int)$this->request->getRequiredBodyParam('sequenceId')]);

        foreach (['subject', 'previewText', 'heading', 'message', 'buttonLabel', 'discountCode'] as $attribute) {
            if (($value = $this->request->getBodyParam($attribute)) !== null) {
                $email->$attribute = trim((string)$value) === '' && $attribute !== 'subject' ? null : (string)$value;
            }
        }

        if (($delay = $this->request->getBodyParam('delay')) !== null) {
            $email->delayMinutes = Duration::toMinutes($delay) ?? 0;
        }

        if (($templateId = $this->request->getBodyParam('emailTemplateId')) !== null) {
            $email->emailTemplateId = $templateId ? (int)$templateId : null;
        }

        if (!$email->id && ($position = $this->request->getBodyParam('position'))) {
            $email->position = (int)$position;
        }

        return $email;
    }

    private function _getEmail(int $id): Email
    {
        $email = AbandonedCart::$plugin->getEmails()->getEmailById($id);

        if (!$email) {
            throw new NotFoundHttpException('Email not found');
        }

        return $email;
    }

    private function _getOrder(): ?Order
    {
        $orderId = (int)$this->request->getBodyParam('orderId');

        return $orderId ? Order::find()->id($orderId)->status(null)->one() : null;
    }

    private function _serialize(Email $email): array
    {
        return [
            'id' => $email->id,
            'sequenceId' => $email->sequenceId,
            'position' => $email->position,
            'delayMinutes' => $email->delayMinutes,
            'delayLabel' => Duration::humanize($email->delayMinutes),
            'subject' => $email->subject,
            'previewText' => $email->previewText,
            'heading' => $email->heading,
            'message' => $email->message,
            'buttonLabel' => $email->buttonLabel,
            'discountCode' => $email->discountCode,
            'emailTemplateId' => $email->emailTemplateId,
            'templateName' => $email->getEmailTemplate()?->name,
        ];
    }
}

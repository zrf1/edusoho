<?php

namespace Biz\OrderFacade\Product;

use Biz\Accessor\AccessorInterface;
use Biz\Course\Service\CourseService;
use Biz\Course\Service\CourseSetService;
use Biz\Course\Service\MemberService;
use Codeages\Biz\Framework\Order\Callback\PaidCallback;
use Codeages\Biz\Framework\Service\Exception\InvalidArgumentException;

class CourseProduct extends Product implements PaidCallback, Owner, Refund
{
    const TYPE = 'course';

    public $showTemplate = 'order/show/course-item.html.twig';

    public $targetType = self::TYPE;

    public $courseSet;

    public function init(array $params)
    {
        $this->targetId = $params['targetId'];
        $course = $this->getCourseService()->getCourse($this->targetId);
        $this->backUrl = array('routing' => 'course_show', 'params' => array('id' => $course['id']));
        $this->successUrl = array('my_course_show', array('id' => $this->targetId));
        $this->title = $course['title'];
        $this->courseSet = $this->getCourseSetService()->getCourseSet($course['courseSetId']);
        $this->price = $course['price'];
        $this->maxRate = $course['maxRate'];
    }

    public function validate()
    {
        $access = $this->getCourseService()->canJoinCourse($this->targetId);

        if ($access['code'] !== AccessorInterface::SUCCESS) {
            throw new InvalidArgumentException($access['msg']);
        }
    }

    public function paidCallback($orderItem)
    {
        $info = array(
            'orderId' => $orderItem['order_id'],
            'remark' => '',
        );

        if (!$this->getCourseMemberService()->isCourseStudent($orderItem['target_id'], $orderItem['user_id'])) {
            $this->getCourseMemberService()->becomeStudent($orderItem['target_id'], $orderItem['user_id'], $info);
        }

        return PaidCallback::SUCCESS;
    }

    public function afterApplyRefund()
    {
        $user = $this->biz['user'];
        $this->getCourseMemberService()->lockStudent($this->targetId, $user->getId());
    }

    public function afterCancelRefund()
    {
        $user = $this->biz['user'];
        $this->getCourseMemberService()->unlockStudent($this->targetId, $user->getId());
    }

    public function afterRefuseRefund($order)
    {
         $this->getCourseMemberService()->unlockStudent($this->targetId, $order['created_user_id']);
    }

    public function afterAdoptRefund($order)
    {
        $this->exitOwner();
    }

    public function exitOwner($userId)
    {
        $this->getCourseMemberService()->removeStudent($this->targetId, $userId);
    }

    public function getOwner($userId)
    {
        return $this->getCourseMemberService()->getCourseMember($this->targetId, $userId);
    }

    /**
     * @return MemberService
     */
    private function getCourseMemberService()
    {
        return $this->biz->service('Course:MemberService');
    }

    /**
     * @return CourseService
     */
    protected function getCourseService()
    {
        return $this->biz->service('Course:CourseService');
    }

    /**
     * @return CourseSetService
     */
    protected function getCourseSetService()
    {
        return $this->biz->service('Course:CourseSetService');
    }
}

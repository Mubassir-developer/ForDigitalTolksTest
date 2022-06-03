<?php

namespace App\services;

use DTApi\Models\Job;
use DTApi\Events\SessionEnded;
use Event;
use Carbon\Carbon;
use DTApi\Helpers\TeHelper;
use DTApi\Mailers\AppMailer;

class BookingService
{
    function store($data)
    {
        $data['customer_phone_type'] = isset($data['customer_phone_type']) ? 'yes' : 'no';
        $data['customer_physical_type'] = $response['customer_physical_type'] = isset($data['customer_physical_type']) ? 'yes' : 'no';
        if ($data['immediate'] == 'yes') {
            $due_carbon = Carbon::now()->addMinute($immediatetime);
            $data['due'] = $due_carbon->format('Y-m-d H:i:s');
            $data['immediate'] = 'yes';
            $data['customer_phone_type'] = 'yes';
            $response['type'] = 'immediate';
        } else {
            $due = $data['due_date'] . " " . $data['due_time'];
            $response['type'] = 'regular';
            $due_carbon = Carbon::createFromFormat('m/d/Y H:i', $due);
            $data['due'] = $due_carbon->format('Y-m-d H:i:s');
            if ($due_carbon->isPast()) {
                $response['status'] = 'fail';
                $response['message'] = "Can't create booking in past";
                return $response;
            }
        }
        $data['gender'] = in_array('male', $data['job_for']) ? 'male' : 'female';

        if (in_array('certified', $data['job_for'])) {
            $data['certified'] = 'yes';
        } else if (in_array('certified_in_law', $data['job_for'])) {
            $data['certified'] = 'law';
        } else if (in_array('certified_in_helth', $data['job_for'])) {
            $data['certified'] = 'health';
        } else if (in_array('normal', $data['job_for'])) {
            $data['certified'] = 'normal';
            if (in_array('certified', $data['job_for'])) {
                $data['certified'] = 'both';
            } else if (in_array('certified_in_law', $data['job_for'])) {
                $data['certified'] = 'n_law';
            } else if (in_array('certified_in_helth', $data['job_for'])) {
                $data['certified'] = 'n_health';
            }
        }
        $job_type = ['rwsconsumer' => 'rws', 'ngo' => 'unpaid', 'paid' => 'paid'];
        $data['job_type'] = $job_type[$consumer_type];

        $data['b_created_at'] = date('Y-m-d H:i:s');
        if (isset($due))
            $data['will_expire_at'] = TeHelper::willExpireAt($due, $data['b_created_at']);
        $data['by_admin'] = isset($data['by_admin']) ? $data['by_admin'] : 'no';
        $job = $user->jobs()->create($data);


        $data['job_for'] = array();
        if ($job->gender != null && $job->gender == 'male') {
            $data['job_for'][] = 'Man';
        } else if ($job->gender != null && $job->gender == 'female') {
            $data['job_for'][] = 'Kvinna';
        }

        if ($job->certified != null) {
            if ($job->certified == 'both') {
                $data['job_for'][] = 'normal';
                $data['job_for'][] = 'certified';
            } else if ($job->certified == 'yes') {
                $data['job_for'][] = 'certified';
            } else {
                $data['job_for'][] = $job->certified;
            }
        }
        $data['customer_town'] = $user->userMeta->city;
        $data['customer_type'] = $user->userMeta->customer_type;

        return $response;
    }


    public function endJob($post_data)
    {
        $completeddate = date('Y-m-d H:i:s');
        $jobid = $post_data["job_id"];
        $job_detail = Job::with('translatorJobRel')->find($jobid);

        if ($job_detail->status != 'started')
            return ['status' => 'success'];

        $duedate = $job_detail->due;
        $start = date_create($duedate);
        $end = date_create($completeddate);
        $diff = date_diff($end, $start);
        $interval = $diff->h . ':' . $diff->i . ':' . $diff->s;
        $job = $job_detail;
        $job->end_at = date('Y-m-d H:i:s');
        $job->status = 'completed';
        $job->session_time = $interval;

        $user = $job->user()->get()->first();
        if (!empty($job->user_email)) {
            $email = $job->user_email;
        } else {
            $email = $user->email;
        }
        $name = $user->name;
        $subject = 'Information om avslutad tolkning för bokningsnummer # ' . $job->id;
        $session_explode = explode(':', $job->session_time);
        $session_time = $session_explode[0] . ' tim ' . $session_explode[1] . ' min';
        $data = [
            'user'         => $user,
            'job'          => $job,
            'session_time' => $session_time,
            'for_text'     => 'faktura'
        ];
        $mailer = new AppMailer();
        $mailer->send($email, $name, $subject, 'emails.session-ended', $data);

        $job->save();

        $tr = $job->translatorJobRel()->where('completed_at', Null)->where('cancel_at', Null)->first();

        Event::fire(new SessionEnded($job, ($post_data['user_id'] == $job->user_id) ? $tr->user_id : $job->user_id));

        $user = $tr->user()->first();
        $email = $user->email;
        $name = $user->name;
        $subject = 'Information om avslutad tolkning för bokningsnummer # ' . $job->id;
        $data = [
            'user'         => $user,
            'job'          => $job,
            'session_time' => $session_time,
            'for_text'     => 'lön'
        ];
        $mailer = new AppMailer();
        $mailer->send($email, $name, $subject, 'emails.session-ended', $data);

        $tr->completed_at = $completeddate;
        $tr->completed_by = $post_data['user_id'];
        $tr->save();
        $response['status'] = 'success';
        return $response;
    }
}

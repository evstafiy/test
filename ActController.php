<?php

namespace App\Http\Controllers;

use App\Http\Requests;

use App\Models\Acts\DictActWork;
use App\Utils;
use App\Models\UserRole;
use App\Models\Equip\Equip;
use App\Models\OrderProblem;
use App\Models\Acts\Act;
use App\Models\Equip\DictEquip;
use App\Models\Equip\HistEquip;
use App\Models\Acts\ActWork;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ActController extends Controller
{
    public function getView()
    {
        $this->authRequired();

        $view = request('view', null);

        switch ($view) {
            case 'editact':
                return $this->_viewEditAct();
            case 'acts':
                return $this->_viewActsContainer();
            case 'actworkequip':
                return $this->_viewActWork();
            case 'editdictactwork':
                return $this->_viewEditDictActWork();
            default:
                return $this->statusError('Unknow view: ' . $view);
        }
    }

    private function _viewActsContainer()
    {
        $acts = Act::getAll([
            'deleted' => 0
        ]);

        return view('act/acts-container', ['acts' => $acts]);
    }

    private function _viewEditAct()
    {
        $this->accessEditTable(Act::TBL_MODEL);

        $data_view = array();

        $act = new Act();

        if (request()->has('id_act')) {
            $id_act = request('id_act');

            $act = Act::find($id_act);

            if ($act == null) {
                $this->statusError('Act by id ' . $id_act . ' not found');
            }
        }
        elseif (request()->has('id_problem')) {
            $id_problem = request('id_problem');

            $order_problem = OrderProblem::find($id_problem);

            if ($order_problem == null) {
                $this->statusError('Order problem by id ' . $id_problem . ' not found');
            }

            $act = Act::getByOrderProblem($order_problem->id);

            $data_view['orderProblem'] = $order_problem;
        }

        $data_view['act'] = $act;

        return view('act/edit-act-modal', $data_view);
    }

    private function _viewActWork()
    {
        $this->accessEditTable(Act::TBL_MODEL);

        $id_dict = (int)request('id_dict', null);

        $dict_equip = DictEquip::getById($id_dict, true);
        if ($dict_equip->id == null) {
            $this->statusError('DictEquip by id: ' . $id_dict . ' not found');
        }

        $actWork = ActWork::withCurrEquip(Equip::withDictEquip($dict_equip));

        return view('act.act-work', [ 'actWork' => $actWork ]);
    }

    private function _viewEditDictActWork()
    {
        $dictWork = new DictActWork();

        $id = request('id', null);
        if ($id != null) {
            $dictWork = DictActWork::find($id);

            if ($dictWork == null) {
                $this->statusError('DictActWork by id: ' . $id . ' not found');
            }
        }

        return view('act.dict_works.edit-dict-act-work-modal', ['dictWork' => $dictWork]);
    }

    public function editAct()
    {
        $this->authRequired();
        $this->accessEditTable(Act::TBL_MODEL);

        $content = json_decode(request()->getContent());

        $act = new Act();

        if (isset($content->id)) {
            $act = Act::find($content->id);

            if ($act == null) {
                $this->statusError('ActWork by id: "' . $content->id . '"" not found');
            }

            if ($act->closed == 1) {
                $this->statusError('Акт закрыт! Для редактирования, нужно открыть акт');
            }

            $act->id_changer = Auth::user()->id;
        }

        $ordProb = OrderProblem::getById($content->id_ord_prob);
        if ($ordProb->id != null) {
            $ordProb->close(request('cause_closed', ''));
        }

        $act->id_ord_problem = $ordProb->id;

        if ($act->id == null) {
            $act->date_create = $content->date_create;
        }

        $act->date_close = $content->date_close;
        $act->date_work = $content->date_work;
        if (isset($content->downtime)) {
            $act->downtime = $content->downtime;
        }

        $act->place_work = $content->place_work;
        $act->distance_work = $content->distance_work;
        $act->work = $content->work;

        $act->id_user_it = $content->id_user_it;

        $act->violated = $content->violated;
        $act->violations = $content->violations;
        $act->note = $content->note;
        $act->equip_efficient = $content->eq_efficient;
        $act->resp_person_absent = $content->resp_person_absent;
        $act->resp_person = $content->resp_person;
        $act->save();

        $this->_updateActWorkMasters($act->id, explode(';', $content->masters));

        if (isset($content->work_eqs))
            $this->_updateActWorks($act, $content->work_eqs);

        $data_response = [
            'status' => Controller::STATUS_OK,
            'id' => $act->id
        ];

        if ($act->id_changer == null)
            $data_response['name_creator'] = Auth::user()->name;
        else
            $data_response['name_changer'] = Auth::user()->name;

        return response()->json($data_response);
    }

    private function _updateActWorkMasters($id_act, $data_masters)
    {
        foreach ($data_masters as $data_mast) {
            $parts = explode('-', $data_mast);
            Utils::updateActWorkMaster($id_act, $parts[0], $parts[1]);
        }
    }

    private function _updateActWorks($act, $data_works)
    {
        foreach ($data_works as $data) {

            $actWork = new ActWork();

            if (isset($data->id)) {
                $actWork = ActWork::find($data->id);

                if ($actWork == null)
                    continue;
            }

            $actWork->id_act = $act->id;
            $actWork->work = $data->work;

            if (isset($data->transfer)) {
                $actWork->transfer = $data->transfer;

                if ($actWork->transfer == 0) {
                    $actWork->cause_work = $data->cause_work;
                    $actWork->diagnosted = $data->diagnosted;
                }
            }

            $currEq = $this->_updateEquip($data->curr_eq, $data->dict_eq, $act->id);
            $actWork->id_curr_equip = $currEq->id;

            if (isset($data->repl_eq)) {
                $replEq = $this->_updateEquip($data->repl_eq, $data->dict_eq, $act->id);
                $actWork->id_repl_equip = $replEq->id;
            }

            $actWork->save();

            if (isset($data->curr_eq->addits)) {
                $this->_updateActWorkAdditionals($data->curr_eq->addits, $actWork, $currEq);
            }

            if (isset($replEq) && isset($data->repl_eq->addits)) {
                $this->_updateActWorkAdditionals($data->repl_eq->addits, $actWork, $replEq);
            }

            if (isset($data->done_works)) {
                $this->_updateActDoneWorks($actWork->id, explode(';', $data->done_works));
            }

        }
    }

    private function _updateActWorkAdditionals($data_addits, $gen_work, $equip)
    {
        foreach ($data_addits as $data) {

            $act_work = new ActWork();

            if (isset($data->id)) {
                $act_work = ActWork::find($data->id);

                if ($act_work == null) {
                    continue;
                }
            }

            $act_work->is_additional = 1;
            $act_work->id_act = $gen_work->id_act;
            $act_work->work = $data->work;

            $data->curr_eq->is_additional = 1;
            $curr_eq = $this->_updateEquip($data->curr_eq, $data->dict_eq, $gen_work->id_act);
            $act_work->id_curr_equip = $curr_eq->id;

            Utils::updateEquipAdditional($equip->id, $curr_eq->id);

            if (isset($data->repl_eq)) {

                $data->repl_eq->is_additional = 1;
                $repl_eq = $this->_updateEquip($data->repl_eq, $data->dict_eq, $gen_work->id_act);
                $act_work->id_repl_equip = $repl_eq->id;

                Utils::updateEquipAdditional($equip->id, $repl_eq->id);
            }

            $act_work->save();

            Utils::updateActWorkAdditional($gen_work->id, $act_work->id);

            if (isset($data->done_works)) {
                $this->_updateActDoneWorks($act_work->id, explode(';', $data->done_works));
            }
        }
    }

    private function _updateEquip($data_eq, $id_dict, $id_act)
    {
        $eq = new Equip();

        if (isset($data_eq->id)) {
            $eq = Equip::find($data_eq->id);

            if ($eq == null) {
                $this->statusError('Equip by id: "' . $data_eq->id . '" not found');
            }
        }

        $eq->id_dict = $id_dict;
        $eq->owner = $data_eq->owner;
        if (isset($data_eq->is_additional)) {
            $eq->is_additional = $data_eq->is_additional;
        }

        $eq->save();

        if (isset($data_eq->values) && $data_eq->values != "") {
            Utils::updateActEquipValues($eq->id, $id_act, $data_eq->values);
        }

        return $eq;
    }

    public function closeAct()
    {
        $this->authRequired();

        if (!Utils::isUserAdmin() && !Utils::isUserRole(UserRole::UR_TECH)) {
            $this->statusError('Недостаточно прав для закрытия акта');
        }

        $id = request('id', null);

        $act = Act::find($id);

        if ($act == null) {
            $this->statusError('ActWork not found by id: "' . $id . '"');
        }

        $actWorks = ActWork::getByAct($act->id, true);
        if ($actWorks->isEmpty()) {
            $this->statusError('Нет работ по акту!');
        }

        $closed = (int)request('closed', 1);
        $act->closed = $closed;
        $act->id_changer = Auth::user()->id;
        $act->save();

        if ($closed == 0) {
            return response()->json([
                'status' => Controller::STATUS_OK,
                'id' => $act->id
            ]);
        }

        $vehicle = $act->getVehicle();

        foreach ($actWorks as $actWork) {

            $currEq = Equip::find($actWork->id_curr_equip);

            if ($currEq == null) {
                continue;
            }

            if ($actWork->work == 0 || $actWork->work == 1) {

                $newHist = HistEquip::getLastByEquip($currEq->id);

                if ($newHist == null) {
                    $newHist = new HistEquip();
                }

                $newHist->id_equip = $currEq->id;
                $newHist->id_vehicle = $vehicle->id;
                $newHist->mount_date = $act->date_work;

                if ($currEq->owner == 1) {
                    $newHist->guarant_date = $act->date_work;
                }

                $newHist->save();

                continue;
            }

            if ($actWork->work == 3) {
                Utils::dismHistEquip($currEq->id, $vehicle->id, $act->date_work);
                continue;
            }

            /*
            if ($act_work_eq->work == 2) {
                $repl_eq = Equip::find($act_work_eq->id_repl_equip);
                if ($repl_eq != null)
                    Utils::updateHistEquip($repl_eq->id, $vehicle->id, $act_work->date_work);
            }
            */

            //TODO протестировать хрень с гарантией!!!

            if ($actWork->work == 2) {

                $replEq = Equip::find($actWork->id_repl_equip);
                if ($replEq == null) {
                    continue;
                }

                $lastCurrHist = Utils::dismHistEquip($currEq->id, $vehicle->id, $act->date_work);
                /*
                if ($last_curr_hist == null) {
                    continue;
                }
                */

                $replHist = $replEq->getLastHist();
                /*
                $repl_hist = HistEquip::getCurrByEquip($repl_eq->id);
                if ($repl_hist == null) {
                    $repl_hist = new HistEquip();
                }
                */

                $replHist->id_equip = $replEq->id;
                $replHist->id_vehicle = $vehicle->id;
                $replHist->mount_date = $act->date_work;

                if (!$act->violated) {

                    if ($replEq->owner == 1) {
                        if ($currEq->isGuarant()) {
                            $replHist->guarant_date = $lastCurrHist->guarant_date;
                            $replHist->guarant_period = $lastCurrHist->getWorkDays();
                            $actWork->guaranted = 1;
                        }
                        else {
                            $replHist->guarant_date = $act->date_work;
                            $replHist->guarant_period = 0;
                            $actWork->guaranted = 0;
                        }
                    }
                    else {
                        $actWork->guaranted = 0;
                    }
                }
                else {
                    $actWork->guaranted = 0;
                }

                $replHist->save();

                $actWork->save();
            }
        }

        return response()->json([
            'status' => Controller::STATUS_OK,
            'id' => $act->id
        ]);
    }

    public function removeAct()
    {
        $this->authRequired();

        $this->accessEditTable(Act::TBL_MODEL);

        $id = request('id', null);

        $act = Act::find($id);

        if ($act == null) {
            $this->statusError('ActWork not found by id: "' . $id . '"');
        }

        $act->deleted = request('deleted', 1);
        $act->id_changer = Auth::user()->id;
        $act->save();

        return response()->json(['status' => Controller::STATUS_OK]);
    }

    public function removeActWork()
    {
        $this->authRequired();

        $this->accessEditTable(ActWork::TBL_MODEL);

        $id = request('id', null);

        $actWork = ActWork::find($id);

        if ($actWork == null) {
            $this->statusError('ActWork not found by id: "' . $id . '"');
        }

        if ($actWork->is_additional) {
            $addit = DB::select('SELECT act_work_additionals.*
                FROM act_work_additionals
                INNER JOIN act_works
                    ON act_work_additionals.id_act_work_add = act_works.id
                WHERE act_works.id = '. $id
            );

            if (count($addit) != 0) {
                Utils::updateActWorkAdditional($addit[0]->id_act_work, $id, 1);
            }
        }

        $actWork->deleted = 1;
        $actWork->save();

        $curr_eq = Equip::find($actWork->id_curr_equip);
        if ($curr_eq != null) {
            $curr_eq->deleted = 1;
            $curr_eq->save();

            $hist = $curr_eq->getLastHist();
            $hist->comment = 'Удалено из акта: ' . $actWork->id_act;
            $hist->dism();
        }

        $repl_eq = Equip::find($actWork->id_repl_equip);
        if ($repl_eq != null) {
            $repl_eq->deleted = 1;
            $repl_eq->save();

            $hist = $repl_eq->getLastHist();
            $hist->comment = 'Удалено из акта: ' . $actWork->id_act;
            $hist->dism();
        }

        return response()->json(['status' => Controller::STATUS_OK]);
    }

    public function editDictActWork()
    {
        $this->authRequired();

        $this->accessEditTable(DictActWork::TBL_MODEL);

        $dict = new DictActWork();

        $id = request('id', null);
        if ($id != null) {
            $dict = DictActWork::find($id);

            if ($dict == null) {
                $this->statusError('DictActWork not found by id: "' . $id . '"');
            }
        }

        $dict->def_name = request('name', '');
        $dict->def_price = request('def_price', 0);
        $dict->save();

        $groups = request('groups', null);
        if ($groups != null) {
            $this->_updateActWorkEquipGroups($dict->id, explode(';', $groups));
        }

        return response()->json([
            'status' => Controller::STATUS_OK,
            'id' => $dict->id
        ]);
    }

    public function removEdictActWork()
    {
        $this->authRequired();

        $this->accessEditTable(DictActWork::TBL_MODEL);

        $id = request('id', null);

        $dict = DictActWork::find($id);

        if ($dict == null) {
            $this->statusError('DictActWork not found by id: "' . $id . '"');
        }

        $dict->deleted = 1;
        $dict->save();

        return response()->json(['status' => Controller::STATUS_OK]);
    }

    private function _updateActWorkEquipGroups($id_work, $data_groups)
    {
        foreach ($data_groups as $data_gr) {
            $parts = explode('-', $data_gr);

            $id_group = null;
            $id_addit_eq = null;

            $id = explode('_', $parts[0]);
            if (count($id) == 2) {
                $id_addit_eq = $id[1];
            }
            else {
                $id_group = $id[0];
            }

            Utils::updateActWorkEqGroup($id_work, $id_group, $id_addit_eq, $parts[1]);
        }
    }

    private function _updateActDoneWorks($id_act_work_eq, $data)
    {
        foreach ($data as $el) {
            $parts = explode('-', $el);
            Utils::updateActDoneWork($id_act_work_eq, $parts[0], $parts[1]);
        }
    }
}

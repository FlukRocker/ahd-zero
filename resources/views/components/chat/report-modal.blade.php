{{-- One shared report dialog for chat lines. State lives in chatLobby
     (reportLine, reportReason, reportNote, reportMessage). Text only via
     x-text / x-model; never x-html. --}}
<div
    x-show="reportLine"
    x-cloak
    class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 p-4"
    @click.self="closeReport()"
    @keydown.escape.window="closeReport()"
>
    <div class="w-full max-w-sm rounded-2xl border border-white/10 bg-neutral-900 p-5 text-white" role="dialog" aria-label="รายงาน">
        <h3 class="mb-3 text-lg font-bold">รายงาน</h3>

        <template x-if="reportMessage === ''">
            <div>
                <label class="flex gap-2 py-1 text-sm"><input type="radio" value="spam" x-model="reportReason"> สแปม</label>
                <label class="flex gap-2 py-1 text-sm"><input type="radio" value="abuse" x-model="reportReason"> ละเมิด</label>
                <label class="flex gap-2 py-1 text-sm"><input type="radio" value="other" x-model="reportReason"> อื่นๆ</label>
                <textarea
                    class="mt-2 w-full resize-none rounded-md bg-black/30 px-3 py-2 text-sm"
                    x-model="reportNote"
                    maxlength="500"
                    rows="3"
                    placeholder="รายละเอียดเพิ่มเติม (ไม่บังคับ)"
                ></textarea>
                <div class="mt-4 flex justify-end gap-3">
                    <button type="button" class="text-sm opacity-70" @click="closeReport()">ยกเลิก</button>
                    <button type="button" class="rounded-md bg-white/15 px-3 py-1.5 text-sm font-medium" :disabled="reportBusy" @click="sendReport()">ส่งรายงาน</button>
                </div>
            </div>
        </template>

        <template x-if="reportMessage !== ''">
            <div>
                <p class="text-sm" x-text="reportMessage"></p>
                <div class="mt-4 flex justify-end">
                    <button type="button" class="rounded-md bg-white/15 px-3 py-1.5 text-sm font-medium" @click="closeReport()">ปิด</button>
                </div>
            </div>
        </template>
    </div>
</div>

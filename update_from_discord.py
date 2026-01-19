import os
from dotenv import load_dotenv
import subprocess
import socket
import re
import asyncio
import discord
from discord import app_commands, Embed
load_dotenv()
TOKEN = os.environ.get("DISCORD_BOT_TOKEN")
if not TOKEN:
    raise ValueError("Environment variable DISCORD_BOT_TOKEN not set!")

CHANNEL_ID = 1410334821086920755

WORKERS = {
    "Ignis": ("wokki_chat_ignis.service", 5001),
    "Aqua": ("wokki_chat_aqua.service", 5002),
    "Terra": ("wokki_chat_terra.service", 5003),
    "Ventus": ("wokki_chat_ventus.service", 5004)
}

DELAY = 2
MONITOR_TIME = 30
PORT_CHECK_RETRIES = 10

RED = "\033[91m"
GREEN = "\033[92m"
YELLOW = "\033[93m"
RESET = "\033[0m"

def is_port_free(port):
    with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as s:
        try:
            s.bind(("0.0.0.0", port))
            return True
        except OSError:
            return False

def get_tracebacks(service):
    result = subprocess.run(
        ["journalctl", "-u", service, "--since", "1 minute ago", "--no-pager", "-o", "cat"],
        capture_output=True, text=True
    )
    logs = result.stdout.splitlines()
    tracebacks = []
    in_traceback = False
    tb_block = []
    for line in logs:
        if "traceback" in line.lower() or in_traceback:
            in_traceback = True
            tb_block.append(line)
            if re.match(r'^\w*Error:.*', line):
                tracebacks.append("\n".join(tb_block))
                tb_block = []
                in_traceback = False
    return tracebacks

def format_time(seconds):
    minutes = int(seconds) // 60
    sec = int(seconds) % 60
    return f"{minutes}m {sec}s"

def render_progress_bar(completed, total, length=20):
	progress = int(length * completed / total)
	return f"[{'#' * progress}{'.' * (length - progress)}] {int((completed/total)*100)}%"

async def restart_and_monitor(service, port, total_remaining_time, discord_message=None, last_messages=None):
	if last_messages is None:
		last_messages = []

	stop_msg = f"🟡 Stopping `{service}`..."
	last_messages.append(stop_msg)
	last_messages = last_messages[-3:]
	if discord_message:
		embed = Embed(title="Worker Update", color=0xFFFF00)
		embed.description = "\n".join(last_messages) + f"\n⏱ Estimated total time: {format_time(total_remaining_time[0])}"
		await discord_message.edit(embed=embed)
	else:
		print(stop_msg)

	stop = subprocess.run(["sudo", "systemctl", "stop", service])
	if stop.returncode != 0:
		text = f"{RED}Failed to stop {service}.{RESET}"
		last_messages.append(text)
		last_messages = last_messages[-3:]
		if discord_message:
			embed = Embed(title="Worker Update", color=0xFF0000)
			embed.description = "\n".join(last_messages)
			await discord_message.edit(embed=embed)
		else:
			print(text)
		return False

	for _ in range(PORT_CHECK_RETRIES):
		if is_port_free(port):
			break
		await asyncio.sleep(1)
		total_remaining_time[0] -= 1

	await asyncio.sleep(DELAY)
	total_remaining_time[0] -= DELAY

	start_msg = f"🟡 Starting `{service}`..."
	last_messages.append(start_msg)
	last_messages = last_messages[-3:]
	if discord_message:
		embed = Embed(title="Worker Update", color=0xFFFF00)
		embed.description = "\n".join(last_messages) + f"\n⏱ Estimated total time: {format_time(total_remaining_time[0])}"
		await discord_message.edit(embed=embed)
	else:
		print(start_msg)

	start = subprocess.run(["sudo", "systemctl", "start", service])
	if start.returncode != 0:
		text = f"{RED}Failed to start {service}.{RESET}"
		last_messages.append(text)
		last_messages = last_messages[-3:]
		if discord_message:
			embed = Embed(title="Worker Update", color=0xFF0000)
			embed.description = "\n".join(last_messages)
			await discord_message.edit(embed=embed)
		else:
			print(text)
		return False

	monitor_msg = f"🟡 `{service}` started. Monitoring for {MONITOR_TIME} seconds..."
	last_messages.append(monitor_msg)
	last_messages = last_messages[-3:]
	if discord_message:
		embed = Embed(title="Worker Update", color=0xFFFF00)
		embed.description = "\n".join(last_messages) + f"\n⏱ Estimated total time: {format_time(total_remaining_time[0])}"
		await discord_message.edit(embed=embed)
	else:
		print(monitor_msg)

	total_ticks = MONITOR_TIME // 10
	for i in range(int(total_ticks)):
		tracebacks = get_tracebacks(service)
		if tracebacks:
			text = f"🔴 Errors detected in {service} logs."
			last_messages.append(text)
			last_messages = last_messages[-3:]
			if discord_message:
				embed = Embed(title="Worker Update", color=0xFF0000)
				embed.description = "\n".join(last_messages) + "\n" + "\n".join(tracebacks)
				await discord_message.edit(embed=embed)
			else:
				print(text)
				for tb in tracebacks:
					print(tb)
			return False

		seconds_left = MONITOR_TIME - (i * 10)
		bar = render_progress_bar(i, total_ticks)
		monitor_tick_msg = f"🟡 Monitoring `{service}`... {seconds_left}s left ⏳\n{bar}"
		last_messages.append(monitor_tick_msg)
		last_messages = last_messages[-3:]
		if discord_message:
			embed = Embed(title="Worker Update", color=0xFFFF00)
			embed.description = "\n".join(last_messages) + f"\n⏱ Estimated total time: {format_time(total_remaining_time[0])}"
			await discord_message.edit(embed=embed)
		total_remaining_time[0] -= 10
		await asyncio.sleep(10)

	success_msg = f"🟢 `{service}` is healthy."
	last_messages.append(success_msg)
	last_messages = last_messages[-3:]
	if discord_message:
		embed = Embed(title="Worker Update", color=0x00FF00)
		embed.description = "\n".join(last_messages)
		await discord_message.edit(embed=embed)
	else:
		print(f"\n{GREEN}{service} is healthy.{RESET}\n")

	return True, last_messages

async def run_workers(worker_names=None, discord_message=None):
	last_messages = []
	if worker_names:
		selected_workers = [WORKERS[w.strip().capitalize()] for w in worker_names if w.strip().capitalize() in WORKERS]
		if not selected_workers and discord_message:
			await discord_message.edit(embed=Embed(title="Worker Update", description="❌ No valid workers specified.", color=0xFF0000))
			return
	else:
		selected_workers = WORKERS.values()

	total_est_time = sum(PORT_CHECK_RETRIES + DELAY + MONITOR_TIME for _ in selected_workers)
	total_remaining_time = [total_est_time]

	if discord_message:
		embed = Embed(title="Worker Update", description=f"⏱ Estimated total time: ~{format_time(total_remaining_time[0])}", color=0xFFFF00)
		await discord_message.edit(embed=embed)

	for s, p in selected_workers:
		success, last_messages = await restart_and_monitor(s, p, total_remaining_time, discord_message, last_messages)
		if not success:
			return

	if discord_message:
		last_messages.append("✅ All workers restarted successfully!")
		last_messages = last_messages[-3:]
		embed = Embed(title="Worker Update", description="\n".join(last_messages), color=0x00FF00)
		await discord_message.edit(embed=embed)

class MyClient(discord.Client):
    def __init__(self, *, intents):
        super().__init__(intents=intents)
        self.tree = app_commands.CommandTree(self)

    async def setup_hook(self):
        await self.tree.sync()

intents = discord.Intents.default()
client = MyClient(intents=intents)

@client.tree.command(name="update", description="Restart selected workers")
@app_commands.describe(workers="Comma-separated list of workers to restart (e.g. Terra,Ventus)")
async def update(interaction: discord.Interaction, workers: str = None):
    if interaction.channel.id != CHANNEL_ID:
        await interaction.response.send_message(
            "This command can only be used in the designated channel.", ephemeral=True
        )
        return

    if not any(role.name == "Development Team" for role in interaction.user.roles):
        await interaction.response.send_message(
            "You are not authorized to use this command.", ephemeral=True
        )
        return

    msg = await interaction.response.send_message("Starting rolling restart...", ephemeral=False)
    discord_message = await interaction.original_response()
    worker_list = [w.strip() for w in workers.split(",")] if workers else None
    await run_workers(worker_list, discord_message)

client.run(TOKEN)